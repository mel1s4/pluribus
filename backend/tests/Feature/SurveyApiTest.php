<?php

namespace Tests\Feature;

use App\Models\Community;
use App\Models\Survey;
use App\Models\SurveyOption;
use App\Models\SurveyVote;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurveyApiTest extends TestCase
{
    use RefreshDatabase;

    private function attach(User $user, Community $community, string $role = 'member'): void
    {
        $user->communities()->syncWithoutDetaching([
            $community->id => ['role' => $role],
        ]);
    }

    public function test_voter_can_create_vote_and_change_vote(): void
    {
        $community = Community::current();
        $voter = User::factory()->create([
            'user_type' => 'member',
            'voting_id' => '123456',
        ]);
        $this->attach($voter, $community);

        $create = $this->actingAs($voter)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/surveys', [
                'title' => 'Budget priority',
                'description' => 'Pick one area',
                'options' => ['Parks', 'Schools'],
            ])
            ->assertCreated()
            ->assertJsonPath('survey.title', 'Budget priority');

        $surveyId = (int) $create->json('survey.id');
        $options = $create->json('survey.options');
        $this->assertCount(2, $options);
        $firstOptionId = (int) $options[0]['id'];
        $secondOptionId = (int) $options[1]['id'];

        $this->actingAs($voter)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->putJson("/api/surveys/{$surveyId}/vote", ['option_id' => $firstOptionId])
            ->assertOk()
            ->assertJsonPath('survey.user_option_id', $firstOptionId)
            ->assertJsonPath('survey.total_votes', 1);

        $this->actingAs($voter)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->putJson("/api/surveys/{$surveyId}/vote", ['option_id' => $secondOptionId])
            ->assertOk()
            ->assertJsonPath('survey.user_option_id', $secondOptionId)
            ->assertJsonPath('survey.total_votes', 1);

        $this->assertDatabaseHas('survey_votes', [
            'survey_id' => $surveyId,
            'user_id' => $voter->id,
            'survey_option_id' => $secondOptionId,
        ]);
        $this->assertDatabaseCount('survey_votes', 1);
    }

    public function test_member_without_voting_id_can_list_and_show_but_not_create_or_vote(): void
    {
        $community = Community::current();
        $author = User::factory()->create([
            'user_type' => 'member',
            'voting_id' => '654321',
        ]);
        $reader = User::factory()->create([
            'user_type' => 'member',
            'voting_id' => null,
        ]);
        $this->attach($author, $community);
        $this->attach($reader, $community);

        $survey = Survey::query()->create([
            'community_id' => $community->id,
            'author_id' => $author->id,
            'title' => 'Open question',
            'status' => Survey::STATUS_OPEN,
        ]);
        $option = SurveyOption::query()->create([
            'survey_id' => $survey->id,
            'label' => 'Yes',
            'sort_order' => 0,
        ]);
        SurveyVote::query()->create([
            'survey_id' => $survey->id,
            'user_id' => $author->id,
            'survey_option_id' => $option->id,
        ]);

        $this->actingAs($reader)
            ->getJson('/api/surveys?community_id='.$community->id)
            ->assertOk()
            ->assertJsonPath('data.0.id', $survey->id);

        $this->actingAs($reader)
            ->getJson("/api/surveys/{$survey->id}")
            ->assertOk()
            ->assertJsonPath('survey.can_vote', false)
            ->assertJsonPath('survey.total_votes', 1);

        $this->actingAs($reader)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/surveys', [
                'title' => 'Blocked',
                'options' => ['A', 'B'],
            ])
            ->assertForbidden();

        $this->actingAs($reader)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->putJson("/api/surveys/{$survey->id}/vote", ['option_id' => $option->id])
            ->assertForbidden();
    }

    public function test_cannot_vote_on_closed_survey(): void
    {
        $community = Community::current();
        $voter = User::factory()->create([
            'user_type' => 'member',
            'voting_id' => '111111',
        ]);
        $this->attach($voter, $community);

        $survey = Survey::query()->create([
            'community_id' => $community->id,
            'author_id' => $voter->id,
            'title' => 'Closed poll',
            'status' => Survey::STATUS_CLOSED,
        ]);
        $option = SurveyOption::query()->create([
            'survey_id' => $survey->id,
            'label' => 'A',
            'sort_order' => 0,
        ]);

        $this->actingAs($voter)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->putJson("/api/surveys/{$survey->id}/vote", ['option_id' => $option->id])
            ->assertForbidden();
    }

    public function test_user_from_other_community_cannot_view_or_vote(): void
    {
        $community = Community::current();
        $other = Community::query()->create([
            'name' => 'Other Town',
            'slug' => 'other-town',
        ]);

        $author = User::factory()->create([
            'user_type' => 'member',
            'voting_id' => '222222',
        ]);
        $outsider = User::factory()->create([
            'user_type' => 'member',
            'voting_id' => '333333',
        ]);
        $this->attach($author, $community);
        $this->attach($outsider, $other);

        $survey = Survey::query()->create([
            'community_id' => $community->id,
            'author_id' => $author->id,
            'title' => 'Local only',
            'status' => Survey::STATUS_OPEN,
        ]);
        $option = SurveyOption::query()->create([
            'survey_id' => $survey->id,
            'label' => 'A',
            'sort_order' => 0,
        ]);

        $this->actingAs($outsider)
            ->getJson("/api/surveys/{$survey->id}")
            ->assertForbidden();

        $this->actingAs($outsider)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->putJson("/api/surveys/{$survey->id}/vote", ['option_id' => $option->id])
            ->assertForbidden();
    }

    public function test_cannot_change_options_after_votes_exist(): void
    {
        $community = Community::current();
        $voter = User::factory()->create([
            'user_type' => 'member',
            'voting_id' => '444444',
        ]);
        $this->attach($voter, $community);

        $survey = Survey::query()->create([
            'community_id' => $community->id,
            'author_id' => $voter->id,
            'title' => 'Locked options',
            'status' => Survey::STATUS_OPEN,
        ]);
        $option = SurveyOption::query()->create([
            'survey_id' => $survey->id,
            'label' => 'Original',
            'sort_order' => 0,
        ]);
        SurveyOption::query()->create([
            'survey_id' => $survey->id,
            'label' => 'Second',
            'sort_order' => 1,
        ]);
        SurveyVote::query()->create([
            'survey_id' => $survey->id,
            'user_id' => $voter->id,
            'survey_option_id' => $option->id,
        ]);

        $this->actingAs($voter)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->patchJson("/api/surveys/{$survey->id}", [
                'options' => ['New A', 'New B'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['options']);
    }

    public function test_multiple_selection_without_ranking(): void
    {
        $community = Community::current();
        $voter = User::factory()->create([
            'user_type' => 'member',
            'voting_id' => '555555',
        ]);
        $this->attach($voter, $community);

        $create = $this->actingAs($voter)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/surveys', [
                'title' => 'Pick many',
                'allow_multiple' => true,
                'options' => ['A', 'B', 'C'],
            ])
            ->assertCreated();

        $surveyId = (int) $create->json('survey.id');
        $opts = $create->json('survey.options');
        $idA = (int) $opts[0]['id'];
        $idB = (int) $opts[1]['id'];

        $this->actingAs($voter)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->putJson("/api/surveys/{$surveyId}/vote", [
                'selections' => [
                    ['option_id' => $idA],
                    ['option_id' => $idB],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('survey.user_selections', fn ($rows) => count($rows) === 2);

        $this->assertDatabaseCount('survey_votes', 2);
    }

    public function test_ranked_survey_requires_consecutive_ranks(): void
    {
        $community = Community::current();
        $voter = User::factory()->create([
            'user_type' => 'member',
            'voting_id' => '666666',
        ]);
        $this->attach($voter, $community);

        $create = $this->actingAs($voter)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/surveys', [
                'title' => 'Ranked',
                'allow_multiple' => true,
                'require_ranked' => true,
                'options' => ['First', 'Second'],
            ])
            ->assertCreated();

        $surveyId = (int) $create->json('survey.id');
        $opts = $create->json('survey.options');

        $this->actingAs($voter)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->putJson("/api/surveys/{$surveyId}/vote", [
                'selections' => [
                    ['option_id' => (int) $opts[0]['id'], 'rank' => 1],
                    ['option_id' => (int) $opts[1]['id'], 'rank' => 3],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['selections']);
    }

    public function test_custom_option_visible_to_other_member(): void
    {
        $community = Community::current();
        $author = User::factory()->create([
            'user_type' => 'member',
            'voting_id' => '777777',
        ]);
        $voter = User::factory()->create([
            'user_type' => 'member',
            'voting_id' => '888888',
        ]);
        $this->attach($author, $community);
        $this->attach($voter, $community);

        $create = $this->actingAs($author)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/surveys', [
                'title' => 'Open options',
                'allow_add_options' => true,
                'options' => ['Preset', 'Other'],
            ])
            ->assertCreated();

        $surveyId = (int) $create->json('survey.id');

        $this->actingAs($voter)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->putJson("/api/surveys/{$surveyId}/vote", [
                'selections' => [
                    ['custom_label' => 'From voter'],
                ],
            ])
            ->assertOk();

        $this->actingAs($author)
            ->getJson("/api/surveys/{$surveyId}")
            ->assertOk()
            ->assertJsonPath('survey.options', fn ($options) => collect($options)->contains(
                fn ($o) => $o['label'] === 'From voter' && $o['is_custom'] === true
            ));
    }

    public function test_custom_label_rejected_when_not_allowed(): void
    {
        $community = Community::current();
        $voter = User::factory()->create([
            'user_type' => 'member',
            'voting_id' => '999999',
        ]);
        $this->attach($voter, $community);

        $create = $this->actingAs($voter)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/surveys', [
                'title' => 'Fixed options',
                'allow_add_options' => false,
                'options' => ['A', 'B'],
            ])
            ->assertCreated();

        $surveyId = (int) $create->json('survey.id');

        $this->actingAs($voter)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->putJson("/api/surveys/{$surveyId}/vote", [
                'selections' => [
                    ['custom_label' => 'Not allowed'],
                ],
            ])
            ->assertUnprocessable();
    }

    public function test_cannot_change_modalities_after_votes(): void
    {
        $community = Community::current();
        $voter = User::factory()->create([
            'user_type' => 'member',
            'voting_id' => '101010',
        ]);
        $this->attach($voter, $community);

        $survey = Survey::query()->create([
            'community_id' => $community->id,
            'author_id' => $voter->id,
            'title' => 'Modalities locked',
            'status' => Survey::STATUS_OPEN,
            'allow_multiple' => false,
        ]);
        $option = SurveyOption::query()->create([
            'survey_id' => $survey->id,
            'label' => 'Only',
            'sort_order' => 0,
        ]);
        SurveyVote::query()->create([
            'survey_id' => $survey->id,
            'user_id' => $voter->id,
            'survey_option_id' => $option->id,
        ]);

        $this->actingAs($voter)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->patchJson("/api/surveys/{$survey->id}", [
                'allow_multiple' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['allow_multiple']);
    }
}
