<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpsertCartItemRequest;
use App\Http\Resources\CartItemResource;
use App\Models\CartItem;
use App\Models\PlaceOffer;
use App\Models\Table;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        assert($user !== null);
        $items = CartItem::query()
            ->where('user_id', $user->id)
            ->with(['offer.place', 'table'])
            ->orderByDesc('updated_at')
            ->get();

        $lines = CartItemResource::collection($items)->resolve();
        $groups = $this->groupLinesByPlace($lines);
        $total = $this->sumMoneyFromLines($lines);

        return response()->json([
            'items' => $lines,
            'groups' => $groups,
            'line_count' => (int) $items->sum('quantity'),
            'total' => $total,
        ]);
    }

    public function upsertItem(UpsertCartItemRequest $request): JsonResponse
    {
        $user = $request->user();
        assert($user !== null);
        $offer = PlaceOffer::query()->with('place')->findOrFail((int) $request->validated('place_offer_id'));
        $this->assertOfferVisibleToBuyer($request, $offer);
        $tableId = $request->validated('table_id');
        if ($tableId === null) {
            $ctx = $request->session()->get('active_table_context');
            if (is_array($ctx) && (int) ($ctx['place_id'] ?? 0) === (int) $offer->place_id) {
                $tableId = (int) ($ctx['table_id'] ?? 0);
                if ($tableId <= 0) {
                    $tableId = null;
                }
            }
        }
        if ($tableId !== null) {
            $table = Table::query()->findOrFail((int) $tableId);
            if ((int) $table->place_id !== (int) $offer->place_id) {
                abort(422, 'Selected table does not belong to this place.');
            }
        }

        $qty = (int) $request->validated('quantity');
        if ($qty <= 0) {
            CartItem::query()
                ->where('user_id', $user->id)
                ->where('place_offer_id', $offer->id)
                ->delete();

            return response()->json(['ok' => true]);
        }

        CartItem::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'place_offer_id' => $offer->id,
            ],
            [
                'quantity' => $qty,
                'table_id' => $tableId,
            ]
        );

        return response()->json(['ok' => true]);
    }

    public function removeItem(Request $request, PlaceOffer $placeOffer): JsonResponse
    {
        $user = $request->user();
        assert($user !== null);
        $placeOffer->loadMissing('place');
        $this->assertOfferVisibleToBuyer($request, $placeOffer);

        CartItem::query()
            ->where('user_id', $user->id)
            ->where('place_offer_id', $placeOffer->id)
            ->delete();

        return response()->json(['ok' => true]);
    }

    public function clear(Request $request): JsonResponse
    {
        $user = $request->user();
        assert($user !== null);
        CartItem::query()->where('user_id', $user->id)->delete();

        return response()->json(['ok' => true]);
    }

    private function assertOfferVisibleToBuyer(Request $request, PlaceOffer $offer): void
    {
        $place = $offer->place;
        if ($place === null) {
            abort(404);
        }
        $this->authorize('view', $place);
        $user = $request->user();
        assert($user !== null);
        if ($user->can('update', $place)) {
            return;
        }
        $uid = (int) $user->id;
        $ok = PlaceOffer::query()
            ->where('id', $offer->id)
            ->where('place_id', $place->id)
            ->visibleToUser($uid)
            ->exists();
        if (! $ok) {
            abort(403);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array{place: array<string, mixed>, items: list<array<string, mixed>>, subtotal: string}>
     */
    private function groupLinesByPlace(array $lines): array
    {
        $map = [];
        foreach ($lines as $line) {
            $place = $line['place'] ?? null;
            if (! is_array($place) || ! isset($place['id'])) {
                continue;
            }
            $pid = (int) $place['id'];
            if (! isset($map[$pid])) {
                $map[$pid] = [
                    'place' => $place,
                    'items' => [],
                    'subtotal' => '0.00',
                ];
            }
            $map[$pid]['items'][] = $line;
        }
        foreach ($map as &$g) {
            $sum = '0.00';
            foreach ($g['items'] as $item) {
                $qty = (int) ($item['quantity'] ?? 0);
                $price = (string) (($item['offer'] ?? [])['price'] ?? '0');
                $sum = $this->addDecimalStrings($sum, $this->multiplyMoney($price, $qty));
            }
            $g['subtotal'] = $sum;
        }
        unset($g);

        return array_values($map);
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function sumMoneyFromLines(array $lines): string
    {
        $sum = '0.00';
        foreach ($lines as $line) {
            $qty = (int) ($line['quantity'] ?? 0);
            $price = (string) (($line['offer'] ?? [])['price'] ?? '0');
            $sum = $this->addDecimalStrings($sum, $this->multiplyMoney($price, $qty));
        }

        return $sum;
    }

    private function addDecimalStrings(string $a, string $b): string
    {
        return number_format((float) $a + (float) $b, 2, '.', '');
    }

    private function multiplyMoney(string $price, int $qty): string
    {
        return number_format((float) $price * $qty, 2, '.', '');
    }
}
