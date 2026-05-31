<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCommunityProjectRequest;
use App\Http\Requests\UpdateCommunityProjectRequest;
use App\Http\Resources\CommunityProjectResource;
use App\Models\Community;
use App\Models\CommunityProject;
use App\Models\CommunityProjectBudgetItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class CommunityProjectController extends Controller
{
    public function index(Request $request, string $slug): JsonResponse|AnonymousResourceCollection
    {
        $community = Community::query()->where('slug', $slug)->first();
        if ($community === null) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $this->authorize('viewAny', [CommunityProject::class, $community]);

        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);

        $paginator = CommunityProject::query()
            ->where('community_id', $community->id)
            ->with(['proposer:id,name,avatar_path'])
            ->withSum('budgetItems', 'cost')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return CommunityProjectResource::collection($paginator);
    }

    public function store(StoreCommunityProjectRequest $request, string $slug): JsonResponse
    {
        $community = Community::query()->where('slug', $slug)->firstOrFail();
        $userId = (int) $request->user()->id;

        $validated = $request->validated();
        $budgetItems = $validated['budget_items'] ?? null;
        unset($validated['budget_items']);

        $project = DB::transaction(function () use ($validated, $community, $userId, $budgetItems): CommunityProject {
            $project = CommunityProject::query()->create(array_merge($validated, [
                'community_id' => (int) $community->id,
                'proposer_id' => $userId,
                'status' => CommunityProject::STATUS_OPEN,
            ]));
            if (is_array($budgetItems) && $budgetItems !== []) {
                $this->replaceBudgetItems($project, $budgetItems);
            }

            return $project;
        });

        $project->load(['proposer:id,name,avatar_path', 'community', 'budgetItems']);

        return response()->json([
            'project' => new CommunityProjectResource($project),
        ], 201);
    }

    public function show(Request $request, string $slug, CommunityProject $project): JsonResponse
    {
        $community = Community::query()->where('slug', $slug)->first();
        if ($community === null || (int) $project->community_id !== (int) $community->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $this->authorize('view', $project);

        $project->load([
            'proposer:id,name,avatar_path',
            'community',
            'arguments.author:id,name,avatar_path',
            'budgetItems',
        ]);
        $sorted = $project->arguments
            ->sortBy([
                fn ($a) => $a->parent_id === null ? 0 : 1,
                'parent_id',
                'sort_order',
                'id',
            ])
            ->values();
        $project->setRelation('arguments', $sorted);

        return response()->json([
            'project' => new CommunityProjectResource($project),
        ]);
    }

    public function update(UpdateCommunityProjectRequest $request, string $slug, CommunityProject $project): JsonResponse
    {
        $community = Community::query()->where('slug', $slug)->first();
        if ($community === null || (int) $project->community_id !== (int) $community->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $validated = $request->validated();
        $patchKeys = array_keys($validated);
        abort_unless(count($patchKeys) > 0, 422, 'No fields to update.');

        $budgetItems = null;
        if (array_key_exists('budget_items', $validated)) {
            $budgetItems = $validated['budget_items'];
            unset($validated['budget_items']);
        }

        DB::transaction(function () use ($project, $validated, $budgetItems): void {
            $project->fill($validated);
            $project->save();
            if ($budgetItems !== null) {
                $this->replaceBudgetItems($project, is_array($budgetItems) ? $budgetItems : []);
            }
        });

        $project->load(['proposer:id,name,avatar_path', 'community', 'budgetItems']);

        return response()->json([
            'project' => new CommunityProjectResource($project),
        ]);
    }

    public function destroy(Request $request, string $slug, CommunityProject $project): JsonResponse
    {
        $community = Community::query()->where('slug', $slug)->first();
        if ($community === null || (int) $project->community_id !== (int) $community->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $this->authorize('delete', $project);

        $project->delete();

        return response()->json(null, 204);
    }

    /**
     * @param  list<array{name: string, description?: string|null, cost: float|int|string, sort_order?: int}>  $items
     */
    private function replaceBudgetItems(CommunityProject $project, array $items): void
    {
        $project->budgetItems()->delete();
        $order = 0;
        foreach ($items as $row) {
            CommunityProjectBudgetItem::query()->create([
                'community_project_id' => (int) $project->id,
                'name' => (string) $row['name'],
                'description' => isset($row['description']) ? (string) $row['description'] : null,
                'cost' => (string) $row['cost'],
                'sort_order' => (int) ($row['sort_order'] ?? $order),
            ]);
            $order++;
        }
    }
}
