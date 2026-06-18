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
use App\Support\SurveyBallotValidator;
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
            ->addSelect([
                'participant_count' => SurveyVote::query()
                    ->selectRaw('count(distinct user_id)')
                    ->whereColumn('survey_id', 'surveys.id'),
            ])
            ->with(['options' => fn ($q) => $q->withCount('votes')])
            ->with(['votes' => fn ($q) => $q->where('user_id', (int) $request->user()->id)->with('option')])
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
        $modality = $this->modalityAttributes($validated);

        $survey = DB::transaction(function () use ($validated, $modality, $optionLabels, $community, $user): Survey {
            $survey = Survey::query()->create([
                'community_id' => $community->id,
                'author_id' => $user->id,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'closes_at' => $validated['closes_at'] ?? null,
                'status' => Survey::STATUS_OPEN,
                'allow_multiple' => $modality['allow_multiple'],
                'require_ranked' => $modality['require_ranked'],
                'allow_add_options' => $modality['allow_add_options'],
            ]);

            foreach (array_values($optionLabels) as $index => $label) {
                SurveyOption::query()->create([
                    'survey_id' => $survey->id,
                    'label' => trim((string) $label),
                    'sort_order' => $index,
                    'is_custom' => false,
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

        if ($survey->hasVotes() && $this->hasModalityChange($request)) {
            throw ValidationException::withMessages([
                'allow_multiple' => ['Survey modalities cannot be changed after votes have been cast.'],
            ]);
        }

        $validated = $request->validated();

        if ($survey->hasVotes() && $request->has('options')) {
            throw ValidationException::withMessages([
                'options' => ['Options cannot be changed after votes have been cast.'],
            ]);
        }

        DB::transaction(function () use ($survey, $validated, $request): void {
            $optionLabels = $validated['options'] ?? null;
            unset($validated['options']);

            $modality = $this->modalityAttributes($validated, $survey);
            $fill = array_merge($validated, $modality);
            if ($fill !== []) {
                $survey->fill($fill);
                $survey->normalizeModalityFlags();
                $survey->save();
            }

            if (is_array($optionLabels)) {
                $survey->options()->where('is_custom', false)->delete();
                foreach (array_values($optionLabels) as $index => $label) {
                    SurveyOption::query()->create([
                        'survey_id' => $survey->id,
                        'label' => trim((string) $label),
                        'sort_order' => $index,
                        'is_custom' => false,
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

        $rawSelections = SurveyBallotValidator::normalizeInputPayload($request->all());
        if ($rawSelections === []) {
            throw ValidationException::withMessages([
                'selections' => ['At least one selection is required.'],
            ]);
        }

        $validator = new SurveyBallotValidator;

        DB::transaction(function () use ($survey, $user, $validator, $rawSelections): void {
            $ballot = $validator->resolve($survey, $user, $rawSelections);

            SurveyVote::query()
                ->where('survey_id', $survey->id)
                ->where('user_id', $user->id)
                ->delete();

            foreach ($ballot as $row) {
                SurveyVote::query()->create([
                    'survey_id' => $survey->id,
                    'user_id' => $user->id,
                    'survey_option_id' => $row['survey_option_id'],
                    'rank' => $row['rank'],
                ]);
            }
        });

        $survey->refresh();

        return response()->json([
            'survey' => new SurveyResource($this->loadSurveyForResource($survey, (int) $user->id)),
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{title?: string, description?: string|null, closes_at?: mixed, allow_multiple: bool, require_ranked: bool, allow_add_options: bool}
     */
    private function modalityAttributes(array $validated, ?Survey $existing = null): array
    {
        $allowMultiple = array_key_exists('allow_multiple', $validated)
            ? (bool) $validated['allow_multiple']
            : (bool) ($existing?->allow_multiple ?? false);
        $requireRanked = array_key_exists('require_ranked', $validated)
            ? (bool) $validated['require_ranked']
            : (bool) ($existing?->require_ranked ?? false);
        $allowAddOptions = array_key_exists('allow_add_options', $validated)
            ? (bool) $validated['allow_add_options']
            : (bool) ($existing?->allow_add_options ?? false);

        if (! $allowMultiple) {
            $requireRanked = false;
        }

        return [
            'allow_multiple' => $allowMultiple,
            'require_ranked' => $requireRanked,
            'allow_add_options' => $allowAddOptions,
        ];
    }

    private function hasModalityChange(UpdateSurveyRequest $request): bool
    {
        return $request->has('allow_multiple')
            || $request->has('require_ranked')
            || $request->has('allow_add_options');
    }

    private function loadSurveyForResource(Survey $survey, ?int $userId = null): Survey
    {
        $userId ??= 0;

        $optionsQuery = fn ($q) => $q->withCount('votes');
        if ($survey->require_ranked) {
            $optionsQuery = fn ($q) => $q->withCount('votes')->with(['votes' => fn ($vq) => $vq->whereNotNull('rank')]);
        }

        $survey->load([
            'author:id,name',
            'options' => $optionsQuery,
            'votes' => fn ($q) => $q->when($userId > 0, fn ($inner) => $inner->where('user_id', $userId)->with('option')),
        ]);

        $survey->participant_count = (int) SurveyVote::query()
            ->where('survey_id', $survey->id)
            ->distinct('user_id')
            ->count('user_id');

        return $survey;
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
