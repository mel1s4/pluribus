<?php

namespace App\Http\Resources;

use App\Models\Place;
use App\Models\PlaceRequirementResponse;
use App\Models\User;
use App\Support\PlaceMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * @mixin \App\Models\PlaceRequirement
 */
class PlaceRequirementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var list<string>|null $gallery */
        $gallery = $this->gallery_paths;
        /** @var list<string>|null $tags */
        $tags = $this->tags;
        /** @var list<string>|null $weekdays */
        $weekdays = $this->recurrence_weekdays;

        $place = $this->relationLoaded('place') ? $this->place : null;
        $actor = $request->user();
        $canManageResponses = $actor instanceof User
            && $place instanceof Place
            && Gate::forUser($actor)->allows('update', $place);

        $communityResponses = [];
        $offersMade = [];
        if ($this->relationLoaded('responses')) {
            foreach ($this->responses as $resp) {
                if ($resp->visibility === PlaceRequirementResponse::VISIBILITY_COMMUNITY) {
                    $communityResponses[] = (new PlaceRequirementResponseResource($resp))->toArray($request);
                }
                if ($canManageResponses) {
                    $offersMade[] = (new PlaceRequirementResponseResource($resp))->toArray($request);
                }
            }
        }

        $data = [
            'id' => $this->id,
            'place_id' => $this->place_id,
            'title' => $this->title,
            'description' => $this->description,
            'quantity' => (string) $this->quantity,
            'unit' => $this->unit,
            'recurrence_mode' => $this->recurrence_mode,
            'recurrence_weekdays' => $weekdays ?? [],
            'photo_path' => $this->photo_path,
            'photo_url' => PlaceMedia::publicUrl($this->photo_path),
            'gallery_paths' => $gallery ?? [],
            'gallery_urls' => collect($gallery ?? [])->map(fn (string $p) => PlaceMedia::publicUrl($p))->filter()->values()->all(),
            'tags' => $tags ?? [],
            'visibility_scope' => $this->visibility_scope,
            'audience_ids' => $this->relationLoaded('audiences')
                ? $this->audiences->pluck('id')->map(fn ($id) => (int) $id)->values()->all()
                : [],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'community_responses' => $communityResponses,
        ];

        if ($this->relationLoaded('exampleOffer')) {
            if ($this->exampleOffer !== null) {
                $offerArr = (new PlaceOfferResource($this->exampleOffer))->toArray($request);
                if ($this->exampleOffer->relationLoaded('place') && $this->exampleOffer->place !== null) {
                    $offerArr['source_place'] = [
                        'id' => $this->exampleOffer->place->id,
                        'name' => $this->exampleOffer->place->name,
                    ];
                }
                $data['example_offer'] = $offerArr;
            } else {
                $data['example_offer'] = null;
            }
        } else {
            $data['example_offer'] = null;
        }

        if ($canManageResponses) {
            $data['offers_made'] = $offersMade;
        }

        return $data;
    }
}
