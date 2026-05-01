<?php

namespace App\Http\Controllers\Api;

use App\Events\PlaceOrdersChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderItemTableRequest;
use App\Http\Requests\UpdatePlaceOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Place;
use App\Models\PlaceOffer;
use App\Models\Table;
use App\Support\PlaceMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
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

            $total = '0.00';
            foreach ($cartRows as $row) {
                $offer = $row->offer;
                assert($offer instanceof PlaceOffer);
                $price = (string) $offer->price;
                $qty = (int) $row->quantity;
                $total = $this->addMoney($total, $this->multiplyMoney($price, $qty));
            }

            $order = Order::query()->create([
                'user_id' => $user->id,
                'status' => Order::STATUS_PENDING,
                'total_amount' => $total,
                'notes' => $request->validated('notes') ?? null,
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
        $this->notifyPlaceOrdersListenersForOrder($order);

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
        $order->update(['status' => $request->validated('status')]);
        $order->load(['user:id,name', 'items' => fn ($q) => $q->where('place_id', $place->id)->with(['place', 'table'])]);
        $sub = '0.00';
        foreach ($order->items as $item) {
            $sub = $this->addMoney($sub, (string) $item->subtotal);
        }
        $order->setAttribute('place_subtotal', $sub);

        $this->notifyPlaceOrdersListeners((int) $place->id);

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

        $this->notifyPlaceOrdersListeners((int) $place->id);

        return response()->json([
            'order' => new OrderResource($order),
        ]);
    }

    private function notifyPlaceOrdersListeners(int $placeId): void
    {
        broadcast(new PlaceOrdersChanged($placeId));
    }

    private function notifyPlaceOrdersListenersForOrder(Order $order): void
    {
        $order->loadMissing('items');
        $ids = $order->items->pluck('place_id')->unique()->filter()->values();
        foreach ($ids as $pid) {
            $this->notifyPlaceOrdersListeners((int) $pid);
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
}
