<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectArgumentRequest;
use App\Http\Requests\UpdateProjectArgumentRequest;
use App\Http\Resources\ProjectArgumentResource;
use App\Models\Community;
use App\Models\CommunityProject;
use App\Models\ProjectArgument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunityProjectArgumentController extends Controller
{
    public function store(StoreProjectArgumentRequest $request, string $slug, CommunityProject $project): JsonResponse
    {
        $community = Community::query()->where('slug', $slug)->first();
        if ($community === null || (int) $project->community_id !== (int) $community->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $validated = $request->validated();
        $userId = (int) $request->user()->id;

        $argument = ProjectArgument::query()->create([
            'project_id' => (int) $project->id,
            'parent_id' => isset($validated['parent_id']) ? (int) $validated['parent_id'] : null,
            'stance' => $validated['stance'],
            'title' => $validated['title'],
            'body' => $validated['body'] ?? null,
            'author_id' => $userId,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);

        $argument->load('author:id,name,avatar_path');

        return response()->json([
            'argument' => new ProjectArgumentResource($argument),
        ], 201);
    }

    public function update(UpdateProjectArgumentRequest $request, string $slug, CommunityProject $project, ProjectArgument $argument): JsonResponse
    {
        $community = Community::query()->where('slug', $slug)->first();
        if ($community === null || (int) $project->community_id !== (int) $community->id || (int) $argument->project_id !== (int) $project->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $validated = $request->validated();
        $patchKeys = array_keys($validated);
        abort_unless(count($patchKeys) > 0, 422, 'No fields to update.');

        $argument->fill($validated);
        $argument->save();

        $argument->load('author:id,name,avatar_path');

        return response()->json([
            'argument' => new ProjectArgumentResource($argument),
        ]);
    }

    public function destroy(Request $request, string $slug, CommunityProject $project, ProjectArgument $argument): JsonResponse
    {
        $community = Community::query()->where('slug', $slug)->first();
        if ($community === null || (int) $project->community_id !== (int) $community->id || (int) $argument->project_id !== (int) $project->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $this->authorize('delete', $argument);

        $argument->delete();

        return response()->json(null, 204);
    }
}
