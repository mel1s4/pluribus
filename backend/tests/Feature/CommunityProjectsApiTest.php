<?php

namespace Tests\Feature;

use App\Models\Community;
use App\Models\CommunityProject;
use App\Models\User;
use App\Support\LocaleOptions;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityProjectsApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function statefulHeaders(): array
    {
        return ['Origin' => 'http://localhost:9123'];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function jsonAs(User $user, string $method, string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user)
            ->withHeaders($this->statefulHeaders())
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->json($method, $uri, $data);
    }

    private function makeCommunity(string $slug = 'hub-one'): Community
    {
        return Community::query()->create([
            'name' => 'Hub',
            'slug' => $slug,
            'description' => null,
            'rules' => null,
            'logo' => null,
            'default_language' => LocaleOptions::default(),
            'currency_code' => null,
            'latitude' => null,
            'longitude' => null,
        ]);
    }

    public function test_guest_cannot_list_projects(): void
    {
        $community = $this->makeCommunity();
        $this->withHeaders($this->statefulHeaders())
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->getJson("/api/communities/{$community->slug}/projects")
            ->assertUnauthorized();
    }

    public function test_non_member_forbidden_on_list(): void
    {
        $community = $this->makeCommunity();
        $outsider = User::factory()->create(['user_type' => 'member']);
        $this->jsonAs($outsider, 'GET', "/api/communities/{$community->slug}/projects")
            ->assertForbidden();
    }

    public function test_member_can_create_list_and_show_project(): void
    {
        $community = $this->makeCommunity();
        $member = User::factory()->create(['user_type' => 'member']);
        $member->communities()->attach($community->id, ['role' => 'member']);

        $this->jsonAs($member, 'POST', "/api/communities/{$community->slug}/projects", [
            'title' => 'Solar panels',
            'description' => 'Short',
            'status' => CommunityProject::STATUS_DRAFT,
        ])
            ->assertCreated()
            ->assertJsonPath('project.title', 'Solar panels')
            ->assertJsonPath('project.status', CommunityProject::STATUS_DRAFT);

        $project = CommunityProject::query()->firstOrFail();

        $this->jsonAs($member, 'GET', "/api/communities/{$community->slug}/projects")
            ->assertOk()
            ->assertJsonPath('data.0.id', $project->id);

        $this->jsonAs($member, 'GET', "/api/communities/{$community->slug}/projects/{$project->id}")
            ->assertOk()
            ->assertJsonPath('project.id', $project->id);
    }

    public function test_my_projects_returns_only_proposer_projects(): void
    {
        $c1 = $this->makeCommunity('hub-a');
        $c2 = $this->makeCommunity('hub-b');
        $alice = User::factory()->create(['user_type' => 'member']);
        $bob = User::factory()->create(['user_type' => 'member']);
        $alice->communities()->attach($c1->id, ['role' => 'member']);
        $alice->communities()->attach($c2->id, ['role' => 'member']);
        $bob->communities()->attach($c1->id, ['role' => 'member']);

        $bobProject = CommunityProject::query()->create([
            'community_id' => $c1->id,
            'proposer_id' => $bob->id,
            'title' => 'Bob proposal',
            'description' => null,
            'status' => CommunityProject::STATUS_ACTIVE,
            'has_budget' => false,
            'has_job_positions' => false,
        ]);

        $aliceProject = CommunityProject::query()->create([
            'community_id' => $c2->id,
            'proposer_id' => $alice->id,
            'title' => 'Alice proposal',
            'description' => null,
            'status' => CommunityProject::STATUS_DRAFT,
            'has_budget' => false,
            'has_job_positions' => false,
        ]);

        $res = $this->jsonAs($alice, 'GET', '/api/my-projects')->assertOk();
        $ids = collect($res->json('data'))->pluck('id')->all();
        $this->assertNotContains($bobProject->id, $ids);
        $this->assertContains($aliceProject->id, $ids);
    }

    public function test_member_can_create_project_with_budget_job_positions_and_location(): void
    {
        $community = $this->makeCommunity('geo-budget-hub');
        $member = User::factory()->create(['user_type' => 'member']);
        $member->communities()->attach($community->id, ['role' => 'member']);

        $this->jsonAs($member, 'POST', "/api/communities/{$community->slug}/projects", [
            'title' => 'Park bench',
            'status' => CommunityProject::STATUS_ACTIVE,
            'deadline' => '2026-12-31T23:59:59Z',
            'has_budget' => true,
            'has_job_positions' => true,
            'location_type' => 'point',
            'service_area_type' => 'none',
            'latitude' => 40.7128,
            'longitude' => -74.006,
            'budget_items' => [
                ['name' => 'Wood', 'description' => 'Lumber', 'unit_cost' => '60.25', 'units' => '2'],
                ['name' => 'Labor', 'unit_cost' => '80', 'units' => '1'],
            ],
            'job_positions' => [
                [
                    'title' => 'Carpenter',
                    'tasks' => [
                        ['body' => 'Cut wood'],
                        ['body' => 'Assemble bench'],
                    ],
                ],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('project.has_budget', true)
            ->assertJsonPath('project.has_job_positions', true);

        $project = CommunityProject::query()->firstOrFail();
        $this->assertEqualsWithDelta(40.7128, (float) $project->latitude, 0.000001);
        $this->assertCount(2, $project->budgetItems);
        $this->assertCount(1, $project->jobPositions);
        $this->assertCount(2, $project->jobPositions->first()->tasks);

        $list = $this->jsonAs($member, 'GET', "/api/communities/{$community->slug}/projects")->assertOk();
        $this->assertEqualsWithDelta(200.5, (float) $list->json('data.0.budget_total'), 0.01);
    }

    public function test_index_filters_by_status_and_search(): void
    {
        $community = $this->makeCommunity('filter-hub');
        $member = User::factory()->create(['user_type' => 'member']);
        $member->communities()->attach($community->id, ['role' => 'member']);

        CommunityProject::query()->create([
            'community_id' => $community->id,
            'proposer_id' => $member->id,
            'title' => 'Alpha garden',
            'description' => null,
            'status' => CommunityProject::STATUS_ACTIVE,
            'has_budget' => false,
            'has_job_positions' => false,
        ]);
        CommunityProject::query()->create([
            'community_id' => $community->id,
            'proposer_id' => $member->id,
            'title' => 'Beta draft',
            'description' => null,
            'status' => CommunityProject::STATUS_DRAFT,
            'has_budget' => false,
            'has_job_positions' => false,
        ]);

        $this->jsonAs($member, 'GET', "/api/communities/{$community->slug}/projects?status=draft")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Beta draft');

        $this->jsonAs($member, 'GET', "/api/communities/{$community->slug}/projects?q=Alpha")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Alpha garden');
    }

    public function test_proposer_can_update_and_delete_project(): void
    {
        $community = $this->makeCommunity('crud-hub');
        $member = User::factory()->create(['user_type' => 'member']);
        $member->communities()->attach($community->id, ['role' => 'member']);

        $create = $this->jsonAs($member, 'POST', "/api/communities/{$community->slug}/projects", [
            'title' => 'Original',
            'status' => CommunityProject::STATUS_DRAFT,
        ])->assertCreated();

        $projectId = (int) $create->json('project.id');

        $this->jsonAs($member, 'PATCH', "/api/communities/{$community->slug}/projects/{$projectId}", [
            'title' => 'Updated',
            'status' => CommunityProject::STATUS_COMPLETED,
        ])
            ->assertOk()
            ->assertJsonPath('project.title', 'Updated')
            ->assertJsonPath('project.status', CommunityProject::STATUS_COMPLETED);

        $this->jsonAs($member, 'DELETE', "/api/communities/{$community->slug}/projects/{$projectId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('community_projects', ['id' => $projectId]);
    }

    public function test_has_budget_requires_budget_items(): void
    {
        $community = $this->makeCommunity('budget-req-hub');
        $member = User::factory()->create(['user_type' => 'member']);
        $member->communities()->attach($community->id, ['role' => 'member']);

        $this->jsonAs($member, 'POST', "/api/communities/{$community->slug}/projects", [
            'title' => 'No lines',
            'has_budget' => true,
            'budget_items' => [],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['budget_items']);
    }
}
