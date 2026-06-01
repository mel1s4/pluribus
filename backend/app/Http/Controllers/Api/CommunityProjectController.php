<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCommunityProjectRequest;
use App\Http\Requests\UpdateCommunityProjectRequest;
use App\Http\Resources\CommunityProjectResource;
use App\Models\Community;
use App\Models\CommunityProject;
use App\Models\CommunityProjectBudgetItem;
use App\Models\CommunityProjectJobPosition;
use App\Models\CommunityProjectJobTask;
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

        $query = CommunityProject::query()
            ->where('community_id', $community->id)
            ->with(['proposer:id,name,avatar_path'])
            ->withSum('budgetItems', 'subtotal');

        $status = $request->query('status');
        if (is_string($status) && $status !== '' && in_array($status, CommunityProject::STATUSES, true)) {
            $query->where('status', $status);
        }

        $q = $request->query('q');
        if (is_string($q) && trim($q) !== '') {
            $query->where('title', 'like', '%'.addcslashes(trim($q), '%_\\').'%');
        }

        if ($request->has('has_budget')) {
            $query->where('has_budget', filter_var($request->query('has_budget'), FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->has('has_job_positions')) {
            $query->where('has_job_positions', filter_var($request->query('has_job_positions'), FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->has('has_deadline')) {
            $hasDeadline = filter_var($request->query('has_deadline'), FILTER_VALIDATE_BOOLEAN);
            if ($hasDeadline) {
                $query->whereNotNull('deadline');
            } else {
                $query->whereNull('deadline');
            }
        }

        $paginator = $query
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
        $jobPositions = $validated['job_positions'] ?? null;
        unset($validated['budget_items'], $validated['job_positions']);

        $hasBudget = (bool) ($validated['has_budget'] ?? false);
        $hasJobPositions = (bool) ($validated['has_job_positions'] ?? false);

        $project = DB::transaction(function () use ($validated, $community, $userId, $budgetItems, $jobPositions, $hasBudget, $hasJobPositions): CommunityProject {
            $project = CommunityProject::query()->create(array_merge($validated, [
                'community_id' => (int) $community->id,
                'proposer_id' => $userId,
            ]));
            if ($hasBudget && is_array($budgetItems) && $budgetItems !== []) {
                $this->replaceBudgetItems($project, $budgetItems);
            }
            if ($hasJobPositions && is_array($jobPositions) && $jobPositions !== []) {
                $this->replaceJobPositions($project, $jobPositions);
            }

            return $project;
        });

        $project->load(['proposer:id,name,avatar_path', 'community', 'budgetItems', 'jobPositions.tasks']);

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
            'budgetItems',
            'jobPositions.tasks',
        ]);

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

        $jobPositions = null;
        if (array_key_exists('job_positions', $validated)) {
            $jobPositions = $validated['job_positions'];
            unset($validated['job_positions']);
        }

        DB::transaction(function () use ($project, $validated, $budgetItems, $jobPositions): void {
            $project->fill($validated);
            $project->save();

            if (array_key_exists('has_budget', $validated) && ! (bool) $validated['has_budget']) {
                $project->budgetItems()->delete();
            } elseif ($budgetItems !== null) {
                $this->replaceBudgetItems($project, is_array($budgetItems) ? $budgetItems : []);
            }

            if (array_key_exists('has_job_positions', $validated) && ! (bool) $validated['has_job_positions']) {
                $project->jobPositions()->delete();
            } elseif ($jobPositions !== null) {
                $this->replaceJobPositions($project, is_array($jobPositions) ? $jobPositions : []);
            }
        });

        $project->load(['proposer:id,name,avatar_path', 'community', 'budgetItems', 'jobPositions.tasks']);

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
     * @param  list<array{name: string, description?: string|null, unit_cost: float|int|string, units: float|int|string, sort_order?: int}>  $items
     */
    private function replaceBudgetItems(CommunityProject $project, array $items): void
    {
        $project->budgetItems()->delete();
        $order = 0;
        foreach ($items as $row) {
            $unitCost = (float) $row['unit_cost'];
            $units = (float) $row['units'];
            $subtotal = round($unitCost * $units, 2);
            CommunityProjectBudgetItem::query()->create([
                'community_project_id' => (int) $project->id,
                'name' => (string) $row['name'],
                'description' => isset($row['description']) ? (string) $row['description'] : null,
                'unit_cost' => number_format($unitCost, 2, '.', ''),
                'units' => number_format($units, 2, '.', ''),
                'subtotal' => number_format($subtotal, 2, '.', ''),
                'sort_order' => (int) ($row['sort_order'] ?? $order),
            ]);
            $order++;
        }
    }

    /**
     * @param  list<array{title: string, tasks?: list<array{body: string}>, sort_order?: int}>  $positions
     */
    private function replaceJobPositions(CommunityProject $project, array $positions): void
    {
        $project->jobPositions()->delete();

        $order = 0;
        foreach ($positions as $row) {
            $position = CommunityProjectJobPosition::query()->create([
                'community_project_id' => (int) $project->id,
                'title' => (string) $row['title'],
                'sort_order' => (int) ($row['sort_order'] ?? $order),
            ]);
            $taskOrder = 0;
            $tasks = is_array($row['tasks'] ?? null) ? $row['tasks'] : [];
            foreach ($tasks as $taskRow) {
                CommunityProjectJobTask::query()->create([
                    'job_position_id' => (int) $position->id,
                    'body' => (string) ($taskRow['body'] ?? ''),
                    'sort_order' => $taskOrder,
                ]);
                $taskOrder++;
            }
            $order++;
        }
    }
}
