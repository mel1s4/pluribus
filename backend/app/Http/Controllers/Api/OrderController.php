<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdatePlaceOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Place;
use App\Models\PlaceOffer;
use App\Support\PlaceMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        assert($user !== null);
        $orders = Order::query()
            ->where('user_id', $user->id)
            ->with(['items.place'])
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
                ->with(['offer.place'])
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
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'subtotal' => $subtotal,
                    'offer_snapshot' => $this->snapshotFromOffer($offer),
                ]);
            }

            CartItem::query()->where('user_id', $user->id)->delete();

            return $order->fresh(['items.place']);
        });

        assert($order instanceof Order);

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
        $order->load(['items.place']);

        return response()->json([
            'order' => new OrderResource($order),
        ]);
    }

    public function placeIndex(Request $request, Place $place): JsonResponse
    {
        $this->authorize('update', $place);
        $orders = Order::query()
            ->whereHas('items', fn ($q) => $q->where('place_id', $place->id))
            ->with(['items' => fn ($q) => $q->where('place_id', $place->id)->with('place')])
            ->orderByDesc('created_at')
            ->paginate(20);

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
        $order->load(['items' => fn ($q) => $q->where('place_id', $place->id)->with('place')]);
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
        $order->load(['items' => fn ($q) => $q->where('place_id', $place->id)->with('place')]);
        $sub = '0.00';
        foreach ($order->items as $item) {
            $sub = $this->addMoney($sub, (string) $item->subtotal);
        }
        $order->setAttribute('place_subtotal', $sub);

        return response()->json([
            'order' => new OrderResource($order),
        ]);
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
        return [
            'title' => $offer->title,
            'description' => $offer->description,
            'price' => (string) $offer->price,
            'photo_path' => $offer->photo_path,
            'photo_url' => PlaceMedia::publicUrl($offer->photo_path),
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
