<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommunityPlaceOfferPickResource;
use App\Models\PlaceOffer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CommunityPlaceOfferController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $q = trim((string) $request->query('q', ''));
        $uid = (int) $request->user()->id;

        $query = PlaceOffer::query()
            ->with(['place:id,name', 'audiences:id'])
            ->visibleToUser($uid)
            ->orderByDesc('place_offers.id');

        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($sub) use ($like): void {
                $sub->where('title', 'like', $like)
                    ->orWhereHas('place', fn ($p) => $p->where('name', 'like', $like));
            });
        }

        $perPage = min(50, max(1, (int) $request->query('per_page', 20)));

        return CommunityPlaceOfferPickResource::collection(
            $query->paginate($perPage)->withQueryString()
        );
    }
}
