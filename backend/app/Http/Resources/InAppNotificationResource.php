<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/** @mixin DatabaseNotification */
class InAppNotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var DatabaseNotification $n */
        $n = $this->resource;
        $data = is_array($n->data) ? $n->data : [];

        return [
            'id' => $n->id,
            'kind' => $data['kind'] ?? null,
            'title' => $data['title'] ?? '',
            'body' => $data['body'] ?? '',
            'action' => $data['action'] ?? null,
            'meta' => $data['meta'] ?? null,
            'read_at' => $n->read_at?->toIso8601String(),
            'created_at' => $n->created_at?->toIso8601String(),
        ];
    }
}
