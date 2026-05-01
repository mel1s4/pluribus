<?php

namespace Tests\Feature;

use App\Models\Place;
use App\Models\User;
use App\Models\UserPersonificationAudit;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PersonificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function statefulHeaders(): array
    {
        return ['Origin' => 'http://localhost:9123'];
    }

    private function statefulJson(string $method, string $uri, array $data = []): TestResponse
    {
        return $this->withHeaders($this->statefulHeaders())
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->json($method, $uri, $data);
    }

    private function makePlaceForUser(User $user, string $name): Place
    {
        return Place::query()->create([
            'user_id' => $user->id,
            'name' => $name,
            'description' => null,
            'tags' => null,
            'latitude' => null,
            'longitude' => null,
            'service_area_type' => Place::SERVICE_AREA_NONE,
            'radius_meters' => null,
            'area_geojson' => null,
            'logo_path' => null,
        ]);
    }

    public function test_member_cannot_start_personification(): void
    {
        $member = User::factory()->create(['user_type' => 'member']);
        $target = User::factory()->create(['user_type' => 'member']);

        $this->actingAs($member);

        $this->statefulJson('POST', '/api/personification/start', [
            'target_user_id' => $target->id,
            'password' => 'password',
            'reason' => 'Investigating reported bug in checkout flow',
        ])->assertForbidden();
    }

    public function test_developer_cannot_resolve_admin_target(): void
    {
        $developer = User::factory()->developer()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($developer);

        $this->statefulJson('POST', '/api/personification/resolve', [
            'email' => $admin->email,
        ])->assertForbidden();
    }

    public function test_root_personified_as_member_cannot_update_others_place(): void
    {
        $owner = User::factory()->create(['user_type' => 'member']);
        $member = User::factory()->create(['user_type' => 'member']);
        $root = User::factory()->root()->create();
        $place = $this->makePlaceForUser($owner, 'Owned spot');

        $this->actingAs($root);

        $this->statefulJson('POST', '/api/personification/start', [
            'target_user_id' => $member->id,
            'password' => 'password',
            'reason' => 'Reproducing map issue reported in ticket 9912',
        ])->assertOk();

        $this->statefulJson('PATCH', '/api/places/'.$place->id, [
            'name' => 'Hacked name',
        ])->assertForbidden();
    }

    public function test_start_and_stop_write_audit_rows(): void
    {
        $developer = User::factory()->developer()->create();
        $target = User::factory()->create(['user_type' => 'member']);

        $this->actingAs($developer);

        $this->statefulJson('POST', '/api/personification/start', [
            'target_user_id' => $target->id,
            'password' => 'password',
            'reason' => 'Helping user configure notification preferences',
            'ticket_reference' => 'SUP-1001',
        ])->assertOk();

        $this->assertDatabaseHas('user_personification_audits', [
            'actor_user_id' => $developer->id,
            'target_user_id' => $target->id,
            'action' => UserPersonificationAudit::ACTION_STARTED,
        ]);

        $this->statefulJson('POST', '/api/personification/stop')->assertOk();

        $this->assertDatabaseHas('user_personification_audits', [
            'actor_user_id' => $developer->id,
            'target_user_id' => $target->id,
            'action' => UserPersonificationAudit::ACTION_STOPPED,
        ]);
    }

    public function test_logout_while_personified_logs_forced_logout(): void
    {
        $developer = User::factory()->developer()->create();
        $target = User::factory()->create(['user_type' => 'member']);

        $this->actingAs($developer);

        $this->statefulJson('POST', '/api/personification/start', [
            'target_user_id' => $target->id,
            'password' => 'password',
            'reason' => 'Verifying calendar sync after migration rollout',
        ])->assertOk();

        $this->statefulJson('POST', '/api/logout')->assertOk();

        $this->assertDatabaseHas('user_personification_audits', [
            'actor_user_id' => $developer->id,
            'target_user_id' => $target->id,
            'action' => UserPersonificationAudit::ACTION_FORCED_LOGOUT,
        ]);
    }

    public function test_user_endpoint_includes_personification_payload(): void
    {
        $developer = User::factory()->developer()->create();
        $target = User::factory()->create(['user_type' => 'member', 'name' => 'Target Person']);

        $this->actingAs($developer);

        $this->statefulJson('POST', '/api/personification/start', [
            'target_user_id' => $target->id,
            'password' => 'password',
            'reason' => 'Diagnosing duplicate order emails for member account',
        ])->assertOk();

        $this->statefulJson('GET', '/api/user')
            ->assertOk()
            ->assertJsonPath('user.id', $target->id)
            ->assertJsonPath('user.name', 'Target Person')
            ->assertJsonPath('personification.active', true)
            ->assertJsonPath('personification.actor.id', $developer->id);
    }

    public function test_cannot_start_second_personification_without_stopping(): void
    {
        $developer = User::factory()->developer()->create();
        $targetA = User::factory()->create(['user_type' => 'member']);
        $targetB = User::factory()->create(['user_type' => 'member']);

        $this->actingAs($developer);

        $this->statefulJson('POST', '/api/personification/start', [
            'target_user_id' => $targetA->id,
            'password' => 'password',
            'reason' => 'First support session for billing question ticket 77',
        ])->assertOk();

        $this->statefulJson('POST', '/api/personification/start', [
            'target_user_id' => $targetB->id,
            'password' => 'password',
            'reason' => 'Second session should be rejected until first is stopped',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['target_user_id']);
    }
}
