<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Table;
use App\Models\TableSeating;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TableSessionController extends Controller
{
    public function ping(Request $request): JsonResponse
    {
        $user = $request->user();
        assert($user !== null);
        $ctx = $request->session()->get('active_table_context');
        if (! is_array($ctx)) {
            return response()->json(['ok' => false, 'message' => 'No active table session.'], 422);
        }
        $placeId = (int) ($ctx['place_id'] ?? 0);
        $tableId = (int) ($ctx['table_id'] ?? 0);
        if ($placeId <= 0 || $tableId <= 0) {
            return response()->json(['ok' => false, 'message' => 'No active table session.'], 422);
        }
        $table = Table::query()->whereKey($tableId)->where('place_id', $placeId)->first();
        if ($table === null) {
            $request->session()->forget('active_table_context');
            TableSeating::query()->where('user_id', $user->id)->where('place_id', $placeId)->delete();

            return response()->json(['ok' => false, 'message' => 'Table no longer exists.'], 422);
        }
        TableSeating::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'place_id' => $placeId,
            ],
            [
                'table_id' => $tableId,
                'last_seen_at' => now(),
            ]
        );

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();
        assert($user !== null);
        $ctx = $request->session()->get('active_table_context');
        if (is_array($ctx)) {
            $placeId = (int) ($ctx['place_id'] ?? 0);
            if ($placeId > 0) {
                TableSeating::query()
                    ->where('user_id', $user->id)
                    ->where('place_id', $placeId)
                    ->delete();
            }
        }
        $request->session()->forget('active_table_context');

        return response()->json(['ok' => true]);
    }
}
