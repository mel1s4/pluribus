<?php

namespace Tests\Feature;

use App\Models\Community;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_accepts_youtube_video_url(): void
    {
        $user = User::factory()->create(['user_type' => 'member']);
        Community::current();

        $this->actingAs($user)
            ->postJson('/api/posts', [
                'type' => Post::TYPE_INFO,
                'title' => 'Town hall recording',
                'visibility_scope' => Post::VISIBILITY_PRIVATE,
                'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            ])
            ->assertCreated()
            ->assertJsonPath('post.video_url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');
    }

    public function test_store_accepts_google_drive_file_url(): void
    {
        $user = User::factory()->create(['user_type' => 'member']);
        Community::current();

        $url = 'https://drive.google.com/file/d/1abcDEFghi_jklMNOpqrSTUVwxyz/view?usp=sharing';

        $this->actingAs($user)
            ->postJson('/api/posts', [
                'type' => Post::TYPE_ANNOUNCEMENT,
                'title' => 'Drive clip',
                'visibility_scope' => Post::VISIBILITY_COMMUNITY,
                'video_url' => $url,
            ])
            ->assertCreated()
            ->assertJsonPath('post.video_url', $url);
    }

    public function test_store_rejects_video_url_from_other_hosts(): void
    {
        $user = User::factory()->create(['user_type' => 'member']);
        Community::current();

        $this->actingAs($user)
            ->postJson('/api/posts', [
                'type' => Post::TYPE_INFO,
                'title' => 'Bad video host',
                'visibility_scope' => Post::VISIBILITY_PRIVATE,
                'video_url' => 'https://example.com/video.mp4',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['video_url']);
    }

    public function test_show_includes_video_url(): void
    {
        $user = User::factory()->create(['user_type' => 'member']);
        $community = Community::current();
        $post = Post::query()->create([
            'community_id' => $community->id,
            'author_id' => $user->id,
            'type' => Post::TYPE_INFO,
            'title' => 'Archived stream',
            'visibility_scope' => Post::VISIBILITY_PRIVATE,
            'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
        ]);

        $this->actingAs($user)
            ->getJson("/api/posts/{$post->id}")
            ->assertOk()
            ->assertJsonPath('post.video_url', 'https://youtu.be/dQw4w9WgXcQ');
    }
}
