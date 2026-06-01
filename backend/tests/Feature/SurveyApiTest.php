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
}
