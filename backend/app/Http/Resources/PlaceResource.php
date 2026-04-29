<?php

namespace App\Http\Resources;

use App\Models\Place;
use App\Models\User;
use App\Support\PlaceMedia;
use App\Support\PlaceServiceScheduleNormalizer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Place
 */
class PlaceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lat = $this->latitude;
        $lng = $this->longitude;

        $actor = $request->user();
        $viewerRole = '';
        $canManageAdmins = false;
        if ($actor instanceof User) {
            $viewerRole = $this->resource->roleForUser($actor);
            $canManageAdmins = $viewerRole === 'owner' || $viewerRole === Place::ADMIN_ROLE_FULL_ACCESS;
        }

        $data = [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'viewer_place_role' => $viewerRole,
            'can_manage_admins' => $canManageAdmins,
            'name' => $this->name,
            'slug' => $this->slug,
            'is_public' => (bool) $this->is_public,
            'description' => $this->description,
            'tags' => $this->tags ?? [],
            'location' => [
                'latitude' => $lat,
                'longitude' => $lng,
            ],
            'latitude' => $lat,
            'longitude' => $lng,
            'location_type' => $this->location_type ?? Place::LOCATION_NONE,
            'service_area_type' => $this->service_area_type,
            'radius_meters' => $this->radius_meters,
            'area_geojson' => $this->area_geojson,
            'logo_path' => $this->logo_path,
            'logo_url' => PlaceMedia::publicUrl($this->logo_path),
            'logo_background_color' => $this->logo_background_color,
            'service_schedule' => PlaceServiceScheduleNormalizer::normalize(
                is_array($this->service_schedule) ? $this->service_schedule : []
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->relationLoaded('offers')) {
            $data['offers'] = PlaceOfferResource::collection($this->offers);
        }

        if ($this->relationLoaded('requirements')) {
            $data['requirements'] = PlaceRequirementResource::collection($this->requirements);
        }

        return $data;
    }
}
