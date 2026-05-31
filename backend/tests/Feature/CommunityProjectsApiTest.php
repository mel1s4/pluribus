<?php

namespace Tests\Feature;

use App\Models\Community;
use App\Models\CommunityProject;
use App\Models\ProjectArgument;
use App\Models\User;
use App\Support\LocaleOptions;
use App\Support\ProjectArgumentDepth;
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

    public function test_member_can_create_list_show_and_add_argument(): void
    {
        $community = $this->makeCommunity();
        $member = User::factory()->create(['user_type' => 'member']);
        $member->communities()->attach($community->id, ['role' => 'member']);

        $this->jsonAs($member, 'POST', "/api/communities/{$community->slug}/projects", [
            'title' => 'Solar panels',
            'description' => 'Short',
            'thesis_title' => 'Should we install them?',
            'thesis_body' => 'Details',
        ])
            ->assertCreated()
            ->assertJsonPath('project.title', 'Solar panels')
            ->assertJsonPath('project.thesis_title', 'Should we install them?');

        $project = CommunityProject::query()->firstOrFail();

        $this->jsonAs($member, 'GET', "/api/communities/{$community->slug}/projects")
            ->assertOk()
            ->assertJsonPath('data.0.id', $project->id);

        $this->jsonAs($member, 'GET', "/api/communities/{$community->slug}/projects/{$project->id}")
            ->assertOk()
            ->assertJsonPath('project.id', $project->id);

        $this->jsonAs($member, 'POST', "/api/communities/{$community->slug}/projects/{$project->id}/arguments", [
            'parent_id' => null,
            'stance' => ProjectArgument::STANCE_PRO,
            'title' => 'Saves money',
        ])
            ->assertCreated()
            ->assertJsonPath('argument.title', 'Saves money');

        $arg = ProjectArgument::query()->firstOrFail();
        $this->assertSame((int) $project->id, (int) $arg->project_id);
    }

    public function test_my_projects_includes_proposed_and_participated(): void
    {
        $c1 = $this->makeCommunity('hub-a');
        $c2 = $this->makeCommunity('hub-b');
        $alice = User::factory()->create(['user_type' => 'member']);
        $bob = User::factory()->create(['user_type' => 'member']);
        $alice->communities()->attach($c1->id, ['role' => 'member']);
        $alice->communities()->attach($c2->id, ['role' => 'member']);
        $bob->communities()->attach($c1->id, ['role' => 'member']);

        $p1 = CommunityProject::query()->create([
            'community_id' => $c1->id,
            'proposer_id' => $bob->id,
            'title' => 'Bob proposal',
            'description' => null,
            'thesis_title' => 'Thesis',
            'thesis_body' => null,
            'status' => CommunityProject::STATUS_OPEN,
        ]);

        $p2 = CommunityProject::query()->create([
            'community_id' => $c2->id,
            'proposer_id' => $alice->id,
            'title' => 'Alice proposal',
            'description' => null,
            'thesis_title' => 'T2',
            'thesis_body' => null,
            'status' => CommunityProject::STATUS_OPEN,
        ]);

        ProjectArgument::query()->create([
            'project_id' => $p1->id,
            'parent_id' => null,
            'stance' => ProjectArgument::STANCE_CON,
            'title' => 'Alice comment',
            'body' => null,
            'author_id' => $alice->id,
            'sort_order' => 0,
        ]);

        $res = $this->jsonAs($alice, 'GET', '/api/my-projects')->assertOk();
        $ids = collect($res->json('data'))->pluck('id')->all();
        $this->assertContains($p1->id, $ids, 'Participated-only project should appear');
        $this->assertContains($p2->id, $ids, 'Proposed project should appear');

        $rowP1 = collect($res->json('data'))->firstWhere('id', $p1->id);
        $this->assertNotNull($rowP1);
        $this->assertFalse((bool) $rowP1['is_proposer']);
        $this->assertTrue((bool) $rowP1['has_argued']);

        $rowP2 = collect($res->json('data'))->firstWhere('id', $p2->id);
        $this->assertNotNull($rowP2);
        $this->assertTrue((bool) $rowP2['is_proposer']);
    }

    public function test_member_can_create_project_with_location_budget_and_list_shows_sum(): void
    {
        $community = $this->makeCommunity('geo-budget-hub');
        $member = User::factory()->create(['user_type' => 'member']);
        $member->communities()->attach($community->id, ['role' => 'member']);

        $this->jsonAs($member, 'POST', "/api/communities/{$community->slug}/projects", [
            'title' => 'Park bench',
            'thesis_title' => 'Should we install benches?',
            'location_type' => 'point',
            'service_area_type' => 'none',
            'latitude' => 40.7128,
            'longitude' => -74.006,
            'budget_items' => [
                ['name' => 'Wood', 'description' => 'Lumber', 'cost' => '120.50'],
                ['name' => 'Labor', 'cost' => '80'],
            ],
        ])
            ->assertCreated();

        $project = CommunityProject::query()->firstOrFail();
        $this->assertEqualsWithDelta(40.7128, (float) $project->latitude, 0.000001);
        $this->assertCount(2, $project->budgetItems);

        $list = $this->jsonAs($member, 'GET', "/api/communities/{$community->slug}/projects")->assertOk();
        $this->assertEqualsWithDelta(200.5, (float) $list->json('data.0.budget_sum'), 0.01);
    }

    public function test_argument_depth_limit(): void
    {
        $community = $this->makeCommunity('depth-hub');
        $member = User::factory()->create(['user_type' => 'member']);
        $member->communities()->attach($community->id, ['role' => 'member']);

        $project = CommunityProject::query()->create([
            'community_id' => $community->id,
            'proposer_id' => $member->id,
            'title' => 'P',
            'description' => null,
            'thesis_title' => 'T',
            'thesis_body' => null,
            'status' => CommunityProject::STATUS_OPEN,
        ]);

        $parentId = null;
        for ($i = 1; $i <= ProjectArgumentDepth::MAX_DEPTH; $i++) {
            $res = $this->jsonAs($member, 'POST', "/api/communities/{$community->slug}/projects/{$project->id}/arguments", [
                'parent_id' => $parentId,
                'stance' => $i % 2 === 1 ? ProjectArgument::STANCE_PRO : ProjectArgument::STANCE_CON,
                'title' => "L{$i}",
            ]);
            $res->assertCreated();
            $parentId = (int) $res->json('argument.id');
        }

        $this->jsonAs($member, 'POST', "/api/communities/{$community->slug}/projects/{$project->id}/arguments", [
            'parent_id' => $parentId,
            'stance' => ProjectArgument::STANCE_PRO,
            'title' => 'Too deep',
        ])
            ->assertStatus(422);
    }
}
