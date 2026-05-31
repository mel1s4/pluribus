<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommunityProjectResource;
use App\Models\CommunityProject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MyCommunityProjectsController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $userId = (int) $user->id;

        $communityIds = $user->communities()->pluck('communities.id');

        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);

        $paginator = CommunityProject::query()
            ->select('community_projects.*')
            ->selectRaw('(community_projects.proposer_id = ?) as viewer_is_proposer', [$userId])
            ->withSum('budgetItems', 'cost')
            ->whereIn('community_id', $communityIds)
            ->where(function ($q) use ($userId): void {
                $q->where('proposer_id', $userId)
                    ->orWhereExists(function ($sub) use ($userId): void {
                        $sub->selectRaw('1')
                            ->from('project_arguments')
                            ->whereColumn('project_arguments.project_id', 'community_projects.id')
                            ->where('project_arguments.author_id', $userId);
                    });
            })
            ->withExists(['arguments as viewer_has_argued' => function ($q) use ($userId): void {
                $q->where('author_id', $userId);
            }])
            ->with(['community', 'proposer:id,name,avatar_path'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return CommunityProjectResource::collection($paginator);
    }
}
