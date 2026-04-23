<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function statefulHeaders(): array
    {
        return ['Origin' => 'http://localhost:9123'];
    }

    private function statefulJson(string $method, string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders($this->statefulHeaders())
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->json($method, $uri, $data);
    }

    public function test_login_succeeds_with_valid_credentials(): void
    {
        $user = User::factory()->create();

        $response = $this->statefulJson('POST', '/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', $user->email);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $user = User::factory()->create();

        $response = $this->statefulJson('POST', '/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_returns_authenticated_payload(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->statefulJson('GET', '/api/user');

        $response->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonStructure(['user' => ['capabilities']]);
    }

    public function test_user_returns_unauthenticated_without_session(): void
    {
        $response = $this->statefulJson('GET', '/api/user');

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->statefulJson('POST', '/api/logout')
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_guest_cannot_logout(): void
    {
        $this->statefulJson('POST', '/api/logout')->assertUnauthorized();
    }

    public function test_login_is_throttled_after_many_failures(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->statefulJson('POST', '/api/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertStatus(422);
        }

        $sixth = $this->statefulJson('POST', '/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $sixth->assertStatus(429);
    }

    public function test_login_succeeds_for_root_with_username(): void
    {
        $user = User::factory()->root()->create([
            'password' => 'ChangeMe2026',
        ]);

        $response = $this->statefulJson('POST', '/api/login', [
            'email' => 'root',
            'password' => 'ChangeMe2026',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.username', 'root')
            ->assertJsonPath('user.is_root', true);
    }

    public function test_root_user_short_circuits_gates_that_would_deny(): void
    {
        Gate::define('blocked', fn (User $user) => false);

        $root = User::factory()->root()->create();

        $this->actingAs($root);

        $this->assertTrue(Gate::allows('blocked'));
    }

    public function test_non_root_user_respects_gate_that_denies(): void
    {
        Gate::define('blocked', fn (User $user) => false);

        $user = User::factory()->create();

        $this->actingAs($user);

        $this->assertFalse(Gate::allows('blocked'));
    }
}
