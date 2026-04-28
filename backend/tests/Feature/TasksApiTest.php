<?php

namespace Tests\Feature;

use App\Models\Community;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TasksApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_create_and_complete_task(): void
    {
        $user = User::factory()->create(['user_type' => 'member']);
        Community::current();

        $create = $this->actingAs($user)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/tasks', [
                'title' => 'Prepare meeting notes',
                'visibility_scope' => 'private',
            ])
            ->assertCreated();

        $taskId = (int) $create->json('task.id');
        $this->assertDatabaseHas('tasks', ['id' => $taskId, 'completed_at' => null]);

        $this->actingAs($user)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->patchJson('/api/tasks/'.$taskId, [
                'completed_at' => now()->toIso8601String(),
            ])
            ->assertOk();

        $this->assertDatabaseHas('tasks', ['id' => $taskId]);
        $this->assertDatabaseHas('posts', ['id' => (int) $create->json('task.post_id'), 'type' => 'task']);
    }
}

