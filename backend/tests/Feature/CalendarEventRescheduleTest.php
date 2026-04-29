<?php

namespace Tests\Feature;

use App\Models\Calendar;
use App\Models\Community;
use App\Models\Post;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarEventRescheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_reschedule_updates_post_times(): void
    {
        $user = User::factory()->create(['user_type' => 'member']);
        $community = Community::current();
        $post = Post::query()->create([
            'community_id' => $community->id,
            'author_id' => $user->id,
            'type' => Post::TYPE_EVENT,
            'title' => 'Meetup',
            'visibility_scope' => Post::VISIBILITY_PRIVATE,
            'start_at' => now()->addDay(),
            'all_day' => false,
        ]);

        $newStart = now()->addDays(3)->startOfSecond();

        $this->actingAs($user)
            ->patchJson("/api/events/post/{$post->id}/reschedule", [
                'start_at' => $newStart->toIso8601String(),
                'end_at' => $newStart->copy()->addHour()->toIso8601String(),
                'all_day' => false,
            ])
            ->assertOk()
            ->assertJsonPath('event.entity_type', 'post')
            ->assertJsonPath('event.title', 'Meetup');

        $post->refresh();
        $this->assertTrue($post->start_at->equalTo($newStart));
    }

    public function test_reschedule_updates_task_times(): void
    {
        $user = User::factory()->create(['user_type' => 'member']);
        $community = Community::current();
        $calendar = Calendar::query()->create([
            'community_id' => $community->id,
            'owner_id' => $user->id,
            'name' => 'T',
            'color' => '#22c55e',
            'visibility_scope' => Calendar::VISIBILITY_PRIVATE,
            'is_default' => false,
        ]);
        $task = Task::query()->create([
            'community_id' => $community->id,
            'author_id' => $user->id,
            'calendar_id' => $calendar->id,
            'title' => 'Task A',
            'visibility_scope' => 'private',
            'start_at' => now()->addDay(),
        ]);

        $newStart = now()->addDays(5)->startOfSecond();

        $this->actingAs($user)
            ->patchJson("/api/events/task/{$task->id}/reschedule", [
                'start_at' => $newStart->toIso8601String(),
                'end_at' => $newStart->copy()->addHour()->toIso8601String(),
                'all_day' => false,
            ])
            ->assertOk()
            ->assertJsonPath('event.entity_type', 'task');

        $task->refresh();
        $this->assertTrue($task->start_at->equalTo($newStart));
    }

    public function test_calendar_events_endpoint_returns_posts_for_calendar(): void
    {
        $user = User::factory()->create(['user_type' => 'member']);
        $community = Community::current();
        $calendar = Calendar::query()->create([
            'community_id' => $community->id,
            'owner_id' => $user->id,
            'name' => 'Team',
            'color' => '#3b82f6',
            'visibility_scope' => Calendar::VISIBILITY_PRIVATE,
            'is_default' => false,
        ]);
        Post::query()->create([
            'community_id' => $community->id,
            'author_id' => $user->id,
            'calendar_id' => $calendar->id,
            'type' => Post::TYPE_EVENT,
            'title' => 'Sprint',
            'visibility_scope' => Post::VISIBILITY_PRIVATE,
            'start_at' => now()->addDay(),
        ]);

        $this->actingAs($user)
            ->getJson("/api/calendars/{$calendar->id}/events")
            ->assertOk()
            ->assertJsonPath('events.0.title', 'Sprint')
            ->assertJsonPath('events.0.entity_type', 'post');
    }
}
