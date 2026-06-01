<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSurveyRequest;
use App\Http\Requests\UpdateSurveyRequest;
use App\Http\Requests\VoteSurveyRequest;
use App\Http\Resources\SurveyResource;
use App\Models\Community;
use App\Models\Survey;
use App\Models\SurveyOption;
use App\Models\SurveyVote;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SurveyController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $community = $this->resolveCommunity($request);
        $this->authorize('viewAny', [Survey::class, $community]);

        $surveys = Survey::query()
            ->where('community_id', $community->id)
            ->with(['options' => fn ($q) => $q->withCount('votes')])
            ->with(['votes' => fn ($q) => $q->where('user_id', (int) $request->user()->id)])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return SurveyResource::collection($surveys);
    }

    public function store(StoreSurveyRequest $request): JsonResponse
    {
        $community = $this->resolveCommunity($request);
        $this->authorize('create', [Survey::class, $community]);

        $user = $request->user();
        if (! $user instanceof User || ! $user->hasVotingId()) {
            abort(403, 'A voting ID is required to create surveys.');
        }

        $validated = $request->validated();
        $optionLabels = $validated['options'];
        unset($validated['options']);

        $survey = DB::transaction(function () use ($validated, $optionLabels, $community, $user): Survey {
            $survey = Survey::query()->create([
                'community_id' => $community->id,
                'author_id' => $user->id,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'closes_at' => $validated['closes_at'] ?? null,
                'status' => Survey::STATUS_OPEN,
            ]);

            foreach (array_values($optionLabels) as $index => $label) {
                SurveyOption::query()->create([
                    'survey_id' => $survey->id,
                    'label' => trim((string) $label),
                    'sort_order' => $index,
                ]);
            }

            return $survey;
        });

        return response()->json([
            'survey' => new SurveyResource($this->loadSurveyForResource($survey)),
        ], 201);
    }

    public function show(Request $request, Survey $survey): JsonResponse
    {
        $this->authorize('view', $survey);

        return response()->json([
            'survey' => new SurveyResource($this->loadSurveyForResource($survey, (int) $request->user()->id)),
        ]);
    }

    public function update(UpdateSurveyRequest $request, Survey $survey): JsonResponse
    {
        $this->authorize('update', $survey);

        $validated = $request->validated();

        if ($survey->hasVotes() && $request->has('options')) {
            throw ValidationException::withMessages([
                'options' => ['Options cannot be changed after votes have been cast.'],
            ]);
        }

        DB::transaction(function () use ($survey, $validated): void {
            $optionLabels = $validated['options'] ?? null;
            unset($validated['options']);

            if ($validated !== []) {
                $survey->fill($validated);
                $survey->save();
            }

            if (is_array($optionLabels)) {
                $survey->options()->delete();
                foreach (array_values($optionLabels) as $index => $label) {
                    SurveyOption::query()->create([
                        'survey_id' => $survey->id,
                        'label' => trim((string) $label),
                        'sort_order' => $index,
                    ]);
                }
            }
        });

        $survey->refresh();

        return response()->json([
            'survey' => new SurveyResource($this->loadSurveyForResource($survey, (int) $request->user()->id)),
        ]);
    }

    public function destroy(Request $request, Survey $survey): JsonResponse
    {
        $this->authorize('delete', $survey);
        $survey->delete();

        return response()->json(['ok' => true]);
    }

    public function vote(VoteSurveyRequest $request, Survey $survey): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        if (! $user->can('vote', $survey)) {
            if (! $user->hasVotingId()) {
                abort(403, 'A voting ID is required to vote in surveys.');
            }
            abort(403, 'This survey is not open for voting.');
        }

        $optionId = (int) $request->validated('option_id');
        $optionExists = $survey->options()->whereKey($optionId)->exists();
        if (! $optionExists) {
            throw ValidationException::withMessages([
                'option_id' => ['The selected option is invalid for this survey.'],
            ]);
        }

        SurveyVote::query()->updateOrCreate(
            [
                'survey_id' => $survey->id,
                'user_id' => $user->id,
            ],
            [
                'survey_option_id' => $optionId,
            ]
        );

        $survey->refresh();

        return response()->json([
            'survey' => new SurveyResource($this->loadSurveyForResource($survey, (int) $user->id)),
        ]);
    }

    private function loadSurveyForResource(Survey $survey, ?int $userId = null): Survey
    {
        $userId ??= 0;

        return $survey->load([
            'author:id,name',
            'options' => fn ($q) => $q->withCount('votes'),
            'votes' => fn ($q) => $q->when($userId > 0, fn ($inner) => $inner->where('user_id', $userId)),
        ]);
    }

    private function resolveCommunity(Request $request): Community
    {
        $requested = (int) $request->input('community_id', $request->query('community_id', 0));
        if ($requested > 0) {
            return Community::query()->findOrFail($requested);
        }
        $active = $request->attributes->get('active_community');
        if ($active instanceof Community) {
            return $active;
        }

        return Community::forRequest($request);
    }
}
