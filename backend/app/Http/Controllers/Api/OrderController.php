<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderItemTableRequest;
use App\Http\Requests\UpdatePlaceOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\CartItem;
use App\Models\Community;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Place;
use App\Models\PlaceOffer;
use App\Models\Table;
use App\Models\User;
use App\Notifications\OrderStatusChangedForBuyerNotification;
use App\Notifications\PlaceNewOrderNotification;
use App\Support\OrderCheckoutWalletSettlement;
use App\Support\PlaceMedia;
use App\Support\WalletMoney;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function __construct(
        private OrderCheckoutWalletSettlement $orderCheckoutWalletSettlement,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        assert($user !== null);
        $orders = Order::query()
            ->where('user_id', $user->id)
            ->with(['items.place', 'items.table'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return OrderResource::collection($orders)->response();
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $user = $request->user();
        assert($user !== null);

        $order = DB::transaction(function () use ($user, $request) {
            $cartRows = CartItem::query()
                ->where('user_id', $user->id)
                ->with(['offer.place', 'table'])
                ->lockForUpdate()
                ->get();

            if ($cartRows->isEmpty()) {
                throw ValidationException::withMessages([
                    'cart' => [__('Your cart is empty.')],
                ]);
            }

            foreach ($cartRows as $row) {
                if ($row->offer === null) {
                    throw ValidationException::withMessages([
                        'cart' => [__('An item in your cart is no longer available.')],
                    ]);
                }
                if ($row->table !== null && (int) $row->table->place_id !== (int) $row->offer->place_id) {
                    throw ValidationException::withMessages([
                        'cart' => [__('A selected table does not match the offer place.')],
                    ]);
                }
                $this->authorizeOfferForCheckout($request, $row->offer);
            }

            $community = Community::current();
            $this->assertMemberOfCommunity($user, (int) $community->id);

            $total = '0.00';
            /** @var array<int, string> */
            $placeSubtotals = [];
            foreach ($cartRows as $row) {
                $offer = $row->offer;
                assert($offer instanceof PlaceOffer);
                $price = (string) $offer->price;
                $qty = (int) $row->quantity;
                $line = WalletMoney::normalize($this->multiplyMoney($price, $qty));
                $total = WalletMoney::add($total, $line);
                $pid = (int) $offer->place_id;
                $placeSubtotals[$pid] = WalletMoney::add($placeSubtotals[$pid] ?? '0.00', $line);
            }

            $notes = $request->validated('notes') ?? null;
            $ledgerNote = is_string($notes) && trim($notes) !== '' ? trim($notes) : null;

            $this->orderCheckoutWalletSettlement->settle(
                $user,
                (int) $community->id,
                $total,
                $placeSubtotals,
                $ledgerNote,
            );

            $order = Order::query()->create([
                'user_id' => $user->id,
                'community_id' => (int) $community->id,
                'status' => Order::STATUS_PENDING,
                'total_amount' => $total,
                'payment_method' => Order::PAYMENT_COMMUNITY_WALLET,
                'wallet_settled_at' => now(),
                'notes' => $notes,
            ]);

            foreach ($cartRows as $row) {
                $offer = $row->offer;
                assert($offer instanceof PlaceOffer);
                $price = (string) $offer->price;
                $qty = (int) $row->quantity;
                $subtotal = $this->multiplyMoney($price, $qty);
                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'place_offer_id' => $offer->id,
                    'place_id' => $offer->place_id,
                    'table_id' => $row->table_id,
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'subtotal' => $subtotal,
                    'offer_snapshot' => $this->snapshotFromOffer($offer),
                ]);
            }

            CartItem::query()->where('user_id', $user->id)->delete();

            return $order->fresh(['items.place', 'items.table']);
        });

        assert($order instanceof Order);
        $this->notifyPlacesAboutNewOrder($order);

        return response()->json([
            'order' => new OrderResource($order),
        ], 201);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        assert($user !== null);
        if ((int) $order->user_id !== (int) $user->id) {
            abort(403);
        }
        $order->load(['items.place', 'items.table']);

        return response()->json([
            'order' => new OrderResource($order),
        ]);
    }

    public function placeIndex(Request $request, Place $place): JsonResponse
    {
        $this->authorize('update', $place);
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'place_offer_ids' => ['sometimes', 'array'],
            'place_offer_ids.*' => ['integer'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:64'],
            'statuses' => ['sometimes', 'array'],
            'statuses.*' => ['string', Rule::in(Order::STATUSES)],
        ]);
        $perPage = (int) ($validated['per_page'] ?? 20);
        /** @var list<int> $offerIds */
        $offerIds = array_values(array_unique(array_map('intval', $validated['place_offer_ids'] ?? [])));
        /** @var list<string> $tags */
        $tags = array_values(array_filter($validated['tags'] ?? [], static fn ($t): bool => is_string($t) && $t !== ''));
        /** @var list<string> $statuses */
        $statuses = array_values(array_filter($validated['statuses'] ?? [], static fn ($s): bool => is_string($s) && $s !== ''));

        $query = Order::query()
            ->whereHas('items', fn ($q) => $q->where('place_id', $place->id));

        if ($statuses !== []) {
            $query->whereIn('status', $statuses);
        }

        if ($offerIds !== [] || $tags !== []) {
            $query->where(function ($outer) use ($place, $offerIds, $tags): void {
                foreach ($offerIds as $oid) {
                    $outer->orWhereHas('items', fn ($iq) => $iq->where('place_id', $place->id)->where('place_offer_id', $oid));
                }
                foreach ($tags as $tag) {
                    $outer->orWhereHas('items', function ($iq) use ($place, $tag): void {
                        $iq->where('place_id', $place->id)
                            ->where(function ($w) use ($tag): void {
                                $w->whereHas('offer', fn ($oq) => $oq->whereJsonContains('tags', $tag))
                                    ->orWhereJsonContains('offer_snapshot->tags', $tag);
                            });
                    });
                }
            });
        }

        $orders = $query
            ->with(['user:id,name', 'items' => fn ($q) => $q->where('place_id', $place->id)->with(['place', 'table'])])
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $orders->getCollection()->transform(function (Order $order): Order {
            $sub = '0.00';
            foreach ($order->items as $item) {
                $sub = $this->addMoney($sub, (string) $item->subtotal);
            }
            $order->setAttribute('place_subtotal', $sub);

            return $order;
        });

        return OrderResource::collection($orders)->response();
    }

    public function placeShow(Request $request, Place $place, Order $order): JsonResponse
    {
        $this->authorize('update', $place);
        if (! $order->items()->where('place_id', $place->id)->exists()) {
            abort(404);
        }
        $order->load(['user:id,name', 'items' => fn ($q) => $q->where('place_id', $place->id)->with(['place', 'table'])]);
        $sub = '0.00';
        foreach ($order->items as $item) {
            $sub = $this->addMoney($sub, (string) $item->subtotal);
        }
        $order->setAttribute('place_subtotal', $sub);

        return response()->json([
            'order' => new OrderResource($order),
        ]);
    }

    public function updatePlaceOrder(UpdatePlaceOrderRequest $request, Place $place, Order $order): JsonResponse
    {
        $this->authorize('update', $place);
        if (! $order->items()->where('place_id', $place->id)->exists()) {
            abort(404);
        }
        $previousStatus = (string) $order->status;
        $newStatus = (string) $request->validated('status');
        $order->update(['status' => $newStatus]);
        if ($previousStatus !== $newStatus) {
            $order->refresh();
            $buyer = $order->user;
            if ($buyer instanceof User) {
                $buyer->notify(new OrderStatusChangedForBuyerNotification(
                    (int) $order->id,
                    (string) $order->order_number,
                    (string) $place->name,
                    $previousStatus,
                    $newStatus,
                ));
            }
        }
        $order->load(['user:id,name', 'items' => fn ($q) => $q->where('place_id', $place->id)->with(['place', 'table'])]);
        $sub = '0.00';
        foreach ($order->items as $item) {
            $sub = $this->addMoney($sub, (string) $item->subtotal);
        }
        $order->setAttribute('place_subtotal', $sub);

        return response()->json([
            'order' => new OrderResource($order),
        ]);
    }

    public function reassignTable(
        UpdateOrderItemTableRequest $request,
        Place $place,
        Order $order,
        OrderItem $item
    ): JsonResponse {
        $this->authorize('update', $place);
        if ((int) $item->order_id !== (int) $order->id || (int) $item->place_id !== (int) $place->id) {
            abort(404);
        }
        $tableId = $request->validated('table_id');
        if ($tableId !== null) {
            $table = Table::query()->findOrFail((int) $tableId);
            if ((int) $table->place_id !== (int) $place->id) {
                abort(422, 'Selected table does not belong to this place.');
            }
        }
        $item->update(['table_id' => $tableId]);
        $order->load(['user:id,name', 'items' => fn ($q) => $q->where('place_id', $place->id)->with(['place', 'table'])]);
        $sub = '0.00';
        foreach ($order->items as $line) {
            $sub = $this->addMoney($sub, (string) $line->subtotal);
        }
        $order->setAttribute('place_subtotal', $sub);

        return response()->json([
            'order' => new OrderResource($order),
        ]);
    }

    private function assertMemberOfCommunity(User $user, int $communityId): void
    {
        $exists = $user->communities()->where('communities.id', $communityId)->exists();
        if (! $exists) {
            throw ValidationException::withMessages([
                'cart' => [__('You must be a community member to place wallet-funded orders.')],
            ]);
        }
    }

    private function authorizeOfferForCheckout(Request $request, PlaceOffer $offer): void
    {
        $place = $offer->place;
        if ($place === null) {
            throw ValidationException::withMessages([
                'cart' => [__('Invalid cart item.')],
            ]);
        }
        $this->authorize('view', $place);
        $user = $request->user();
        assert($user !== null);
        if ($user->can('update', $place)) {
            return;
        }
        $ok = PlaceOffer::query()
            ->where('id', $offer->id)
            ->where('place_id', $place->id)
            ->visibleToUser((int) $user->id)
            ->exists();
        if (! $ok) {
            throw ValidationException::withMessages([
                'cart' => [__('An item in your cart is not available.')],
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotFromOffer(PlaceOffer $offer): array
    {
        $tags = $offer->tags;
        if (! is_array($tags)) {
            $tags = [];
        }

        return [
            'place_offer_id' => $offer->id,
            'title' => $offer->title,
            'description' => $offer->description,
            'price' => (string) $offer->price,
            'photo_path' => $offer->photo_path,
            'photo_url' => PlaceMedia::publicUrl($offer->photo_path),
            'tags' => $tags,
            'category' => $offer->category,
        ];
    }

    private function addMoney(string $a, string $b): string
    {
        return number_format((float) $a + (float) $b, 2, '.', '');
    }

    private function multiplyMoney(string $price, int $qty): string
    {
        return number_format((float) $price * $qty, 2, '.', '');
    }

    private function notifyPlacesAboutNewOrder(Order $order): void
    {
        $order->loadMissing(['user:id,name', 'items']);
        $placeIds = $order->items->pluck('place_id')->unique()->filter();
        foreach ($placeIds as $placeId) {
            $place = Place::query()->with(['administrators:id'])->find((int) $placeId);
            if ($place === null) {
                continue;
            }
            $subtotal = '0.00';
            foreach ($order->items->where('place_id', (int) $placeId) as $item) {
                $subtotal = $this->addMoney($subtotal, (string) $item->subtotal);
            }
            $customerName = (string) ($order->user?->name ?? __('Someone'));
            $recipientIds = collect([(int) $place->user_id])
                ->merge($place->administrators->pluck('id')->map(fn ($id): int => (int) $id))
                ->unique()
                ->reject(fn (int $id): bool => $id === (int) $order->user_id)
                ->values();
            foreach ($recipientIds as $uid) {
                User::query()->find($uid)?->notify(new PlaceNewOrderNotification(
                    (int) $place->id,
                    (string) $place->name,
                    (int) $order->id,
                    (string) $order->order_number,
                    $subtotal,
                    $customerName,
                ));
            }
        }
    }
}
