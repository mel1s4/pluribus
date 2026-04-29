<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTableAccessLinkRequest;
use App\Models\Place;
use App\Models\Table;
use App\Models\TableAccessLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TableAccessLinkController extends Controller
{
    public function store(StoreTableAccessLinkRequest $request, Place $place, Table $table): JsonResponse
    {
        $this->authorize('update', $place);
        if ((int) $table->place_id !== (int) $place->id) {
            abort(404);
        }
        $validated = $request->validated();
        $plain = Str::random(48);
        $row = TableAccessLink::query()->create([
            'table_id' => $table->id,
            'created_by' => $request->user()?->id,
            'token_hash' => TableAccessLink::hashPlainToken($plain),
            'max_uses' => $validated['max_uses'] ?? null,
            'uses_count' => 0,
            'expires_at' => isset($validated['expires_in_minutes']) ? now()->addMinutes((int) $validated['expires_in_minutes']) : now()->addDays(14),
            'revoked_at' => null,
        ]);

        return response()->json([
            'access_link' => [
                'id' => $row->id,
                'url' => rtrim((string) config('app.frontend_url'), '/').'/table-access/'.$plain,
                'expires_at' => $row->expires_at?->toIso8601String(),
                'max_uses' => $row->max_uses,
            ],
        ], 201);
    }

    public function rotate(StoreTableAccessLinkRequest $request, Place $place, Table $table): JsonResponse
    {
        $this->authorize('update', $place);
        if ((int) $table->place_id !== (int) $place->id) {
            abort(404);
        }
        TableAccessLink::query()
            ->where('table_id', $table->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        return $this->store($request, $place, $table);
    }

    public function resolve(string $token): JsonResponse
    {
        $row = TableAccessLink::findByPlainToken($token);
        if (! $row instanceof TableAccessLink) {
            return response()->json(['valid' => false, 'reason' => 'invalid_token']);
        }
        $row->loadMissing('table.place');
        $reason = $row->failureReason();
        if ($reason !== null) {
            return response()->json(['valid' => false, 'reason' => $reason]);
        }

        return response()->json([
            'valid' => true,
            'table' => [
                'id' => $row->table?->id,
                'name' => $row->table?->name,
            ],
            'place' => [
                'id' => $row->table?->place?->id,
                'name' => $row->table?->place?->name,
                'slug' => $row->table?->place?->slug,
            ],
        ]);
    }

    public function consume(Request $request, string $token): JsonResponse
    {
        $payload = DB::transaction(function () use ($token): array {
            $row = TableAccessLink::query()
                ->where('token_hash', TableAccessLink::hashPlainToken($token))
                ->lockForUpdate()
                ->first();
            if (! $row instanceof TableAccessLink || ! $row->isUsable()) {
                abort(422, 'Table access link is invalid.');
            }
            $row->increment('uses_count');
            $row->loadMissing('table.place');

            return [
                'table_id' => (int) $row->table_id,
                'place_id' => (int) ($row->table?->place_id ?? 0),
                'place_slug' => (string) ($row->table?->place?->slug ?? ''),
            ];
        });
        $request->session()->put('active_table_context', $payload);

        return response()->json(['context' => $payload]);
    }

    public function revoke(Place $place, Table $table): JsonResponse
    {
        $this->authorize('update', $place);
        if ((int) $table->place_id !== (int) $place->id) {
            abort(404);
        }
        TableAccessLink::query()->where('table_id', $table->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
