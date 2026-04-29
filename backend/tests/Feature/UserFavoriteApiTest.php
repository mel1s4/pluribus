<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserFavorite;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserFavoriteApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_favorites(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->getJson('/api/user-favorites')
            ->assertUnauthorized();
    }

    public function test_user_can_add_list_remove_and_reorder_favorites(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->getJson('/api/user-favorites')
            ->assertOk()
            ->assertJsonPath('favorites', []);

        $this->actingAs($user)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/user-favorites', ['route_key' => 'dashboard'])
            ->assertCreated()
            ->assertJsonPath('favorite.route_key', 'dashboard');

        $this->actingAs($user)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/user-favorites', ['route_key' => 'chats'])
            ->assertCreated();

        $this->actingAs($user)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->getJson('/api/user-favorites')
            ->assertOk();

        $favorites = UserFavorite::query()->where('user_id', $user->id)->orderBy('sort_order')->get();
        $this->assertCount(2, $favorites);

        $this->actingAs($user)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->putJson('/api/user-favorites/reorder', [
                'favorites' => [
                    ['route_key' => 'chats', 'order' => 0],
                    ['route_key' => 'dashboard', 'order' => 1],
                ],
            ])
            ->assertOk();

        $this->assertSame(
            ['chats', 'dashboard'],
            UserFavorite::query()->where('user_id', $user->id)->orderBy('sort_order')->pluck('route_key')->all()
        );

        $this->actingAs($user)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->deleteJson('/api/user-favorites/chats')
            ->assertNoContent();

        $this->actingAs($user)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->getJson('/api/user-favorites')
            ->assertOk()
            ->assertJsonCount(1, 'favorites');
    }

    public function test_invalid_route_key_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/user-favorites', ['route_key' => 'not-a-sidebar-key'])
            ->assertUnprocessable();
    }

    public function test_reorder_requires_exact_set(): void
    {
        $user = User::factory()->create();
        UserFavorite::query()->create([
            'user_id' => $user->id,
            'route_key' => 'map',
            'sort_order' => 0,
        ]);
        UserFavorite::query()->create([
            'user_id' => $user->id,
            'route_key' => 'profile',
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->putJson('/api/user-favorites/reorder', [
                'favorites' => [
                    ['route_key' => 'map', 'order' => 1],
                ],
            ])
            ->assertStatus(422);
    }
}
