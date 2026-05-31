<?php

namespace Tests\Feature;

use App\Models\Community;
use App\Models\Folder;
use App\Models\Group;
use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NoteApiTest extends TestCase
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

    public function test_owner_can_create_update_with_lock_revisions_and_revert(): void
    {
        $owner = User::factory()->create(['user_type' => 'member']);
        $folder = Folder::query()->create([
            'user_id' => $owner->id,
            'name' => 'Docs',
            'parent_id' => null,
            'sort_order' => 0,
        ]);

        $this->jsonAs($owner, 'POST', '/api/notes', [
            'folder_id' => $folder->id,
            'title' => 'Hello',
            'description' => 'Desc',
            'content_markdown' => 'A',
        ])
            ->assertCreated()
            ->assertJsonPath('note.title', 'Hello');

        $note = Note::query()->firstOrFail();
        $sid = (string) Str::uuid();

        $this->jsonAs($owner, 'POST', "/api/notes/{$note->id}/lock", ['session_id' => $sid])->assertOk();

        $this->jsonAs($owner, 'PATCH', "/api/notes/{$note->id}", [
            'title' => 'Hello2',
            'editor_session_id' => $sid,
        ])
            ->assertOk()
            ->assertJsonPath('note.title', 'Hello2');

        $this->assertDatabaseHas('note_revisions', [
            'note_id' => $note->id,
            'title' => 'Hello',
        ]);

        $rid = (int) $note->revisions()->orderByDesc('id')->value('id');
        $this->assertGreaterThan(0, $rid);

        $this->jsonAs($owner, 'POST', "/api/notes/{$note->id}/revert", [
            'revision_id' => $rid,
            'editor_session_id' => $sid,
        ])
            ->assertOk()
            ->assertJsonPath('note.title', 'Hello');

        $this->jsonAs($owner, 'DELETE', "/api/notes/{$note->id}/lock", ['session_id' => $sid])->assertOk();
        $this->jsonAs($owner, 'DELETE', "/api/notes/{$note->id}")->assertOk();
    }

    public function test_non_member_gets_404_on_note(): void
    {
        $owner = User::factory()->create(['user_type' => 'member']);
        $stranger = User::factory()->create(['user_type' => 'member']);
        $folder = Folder::query()->create([
            'user_id' => $owner->id,
            'name' => 'Private',
            'parent_id' => null,
            'sort_order' => 0,
        ]);
        $note = Note::query()->create([
            'folder_id' => $folder->id,
            'author_id' => $owner->id,
            'last_edited_by_user_id' => $owner->id,
            'title' => 'Secret',
            'sort_order' => 0,
        ]);

        $this->jsonAs($stranger, 'GET', "/api/notes/{$note->id}")->assertNotFound();
    }

    public function test_same_user_second_tab_lock_returns_409(): void
    {
        $owner = User::factory()->create(['user_type' => 'member']);
        $folder = Folder::query()->create([
            'user_id' => $owner->id,
            'name' => 'F',
            'parent_id' => null,
            'sort_order' => 0,
        ]);
        $note = Note::query()->create([
            'folder_id' => $folder->id,
            'author_id' => $owner->id,
            'last_edited_by_user_id' => $owner->id,
            'title' => 'N',
            'sort_order' => 0,
        ]);

        $sid1 = (string) Str::uuid();
        $sid2 = (string) Str::uuid();

        $this->jsonAs($owner, 'POST', "/api/notes/{$note->id}/lock", ['session_id' => $sid1])->assertOk();
        $this->jsonAs($owner, 'POST', "/api/notes/{$note->id}/lock", ['session_id' => $sid2])->assertStatus(409);
    }

    public function test_view_collaborator_cannot_patch_note(): void
    {
        $communityId = (int) Community::current()->id;
        $owner = User::factory()->create(['user_type' => 'member']);
        $member = User::factory()->create(['user_type' => 'member']);

        $group = Group::query()->create([
            'community_id' => $communityId,
            'owner_id' => $owner->id,
            'name' => 'Team',
            'description' => null,
        ]);
        $group->members()->attach($owner->id, ['role' => Group::ROLE_OWNER, 'joined_at' => now()]);
        $group->members()->attach($member->id, ['role' => Group::ROLE_MEMBER, 'joined_at' => now()]);

        $folder = Folder::query()->create([
            'user_id' => $owner->id,
            'shared_group_id' => $group->id,
            'name' => 'Shared',
            'parent_id' => null,
            'sort_order' => 0,
        ]);

        $note = Note::query()->create([
            'folder_id' => $folder->id,
            'author_id' => $owner->id,
            'last_edited_by_user_id' => $owner->id,
            'title' => 'Doc',
            'sort_order' => 0,
        ]);

        $this->jsonAs($owner, 'POST', "/api/notes/{$note->id}/collaborators", [
            'user_id' => $member->id,
            'permission' => 'view',
        ])->assertCreated();

        $sid = (string) Str::uuid();
        $this->jsonAs($member, 'POST', "/api/notes/{$note->id}/lock", ['session_id' => $sid])->assertForbidden();

        $this->jsonAs($owner, 'POST', "/api/notes/{$note->id}/lock", ['session_id' => $sid])->assertOk();
        $this->jsonAs($member, 'PATCH', "/api/notes/{$note->id}", [
            'title' => 'Hacked',
            'editor_session_id' => $sid,
        ])->assertForbidden();
    }

    public function test_view_collaborator_cannot_revert_note(): void
    {
        $communityId = (int) Community::current()->id;
        $owner = User::factory()->create(['user_type' => 'member']);
        $member = User::factory()->create(['user_type' => 'member']);

        $group = Group::query()->create([
            'community_id' => $communityId,
            'owner_id' => $owner->id,
            'name' => 'Team',
            'description' => null,
        ]);
        $group->members()->attach($owner->id, ['role' => Group::ROLE_OWNER, 'joined_at' => now()]);
        $group->members()->attach($member->id, ['role' => Group::ROLE_MEMBER, 'joined_at' => now()]);

        $folder = Folder::query()->create([
            'user_id' => $owner->id,
            'shared_group_id' => $group->id,
            'name' => 'Shared',
            'parent_id' => null,
            'sort_order' => 0,
        ]);

        $note = Note::query()->create([
            'folder_id' => $folder->id,
            'author_id' => $owner->id,
            'last_edited_by_user_id' => $owner->id,
            'title' => 'Doc',
            'sort_order' => 0,
        ]);

        $this->jsonAs($owner, 'POST', "/api/notes/{$note->id}/collaborators", [
            'user_id' => $member->id,
            'permission' => 'view',
        ])->assertCreated();

        $sid = (string) Str::uuid();
        $this->jsonAs($owner, 'POST', "/api/notes/{$note->id}/lock", ['session_id' => $sid])->assertOk();
        $this->jsonAs($owner, 'PATCH', "/api/notes/{$note->id}", [
            'title' => 'Doc2',
            'editor_session_id' => $sid,
        ])->assertOk();

        $revisionId = (int) $note->revisions()->orderByDesc('id')->value('id');
        $this->assertGreaterThan(0, $revisionId);

        $memberSid = (string) Str::uuid();
        $this->jsonAs($member, 'POST', "/api/notes/{$note->id}/revert", [
            'revision_id' => $revisionId,
            'editor_session_id' => $memberSid,
        ])->assertForbidden();
    }

    public function test_folder_search_returns_notes_and_matches_tags(): void
    {
        $owner = User::factory()->create(['user_type' => 'member']);
        $folder = Folder::query()->create([
            'user_id' => $owner->id,
            'name' => 'Docs',
            'parent_id' => null,
            'sort_order' => 0,
        ]);

        $this->jsonAs($owner, 'POST', '/api/notes', [
            'folder_id' => $folder->id,
            'title' => 'Other',
            'tags' => ['findme-tag'],
        ])->assertCreated();

        $tagSearch = $this->jsonAs($owner, 'GET', '/api/folders/search', [
            'q' => 'findme-tag',
            'type' => 'note',
        ])->assertOk()->json('notes');
        $tagNotes = is_array($tagSearch['data'] ?? null) ? $tagSearch['data'] : (is_array($tagSearch) ? $tagSearch : []);
        $this->assertNotEmpty($tagNotes);
        $this->assertSame('Other', $tagNotes[0]['title'] ?? null);

        $this->jsonAs($owner, 'POST', '/api/notes', [
            'folder_id' => $folder->id,
            'title' => 'Alpha title',
        ])->assertCreated();

        $titleSearch = $this->jsonAs($owner, 'GET', '/api/folders/search', [
            'q' => 'Alpha',
            'type' => 'note',
        ])->assertOk()->json('notes');
        $titleNotes = is_array($titleSearch['data'] ?? null) ? $titleSearch['data'] : (is_array($titleSearch) ? $titleSearch : []);
        $this->assertNotEmpty($titleNotes);
        $this->assertSame('Alpha title', $titleNotes[0]['title'] ?? null);
    }

    public function test_bulk_move_note_to_other_folder(): void
    {
        $owner = User::factory()->create(['user_type' => 'member']);
        $a = Folder::query()->create([
            'user_id' => $owner->id,
            'name' => 'A',
            'parent_id' => null,
            'sort_order' => 0,
        ]);
        $b = Folder::query()->create([
            'user_id' => $owner->id,
            'name' => 'B',
            'parent_id' => null,
            'sort_order' => 0,
        ]);
        $note = Note::query()->create([
            'folder_id' => $a->id,
            'author_id' => $owner->id,
            'last_edited_by_user_id' => $owner->id,
            'title' => 'Move me',
            'sort_order' => 0,
        ]);

        $this->jsonAs($owner, 'POST', '/api/folders/bulk-move', [
            'target_folder_id' => $b->id,
            'items' => [
                ['type' => 'note', 'id' => $note->id],
            ],
        ])->assertOk();

        $this->assertSame($b->id, (int) $note->fresh()->folder_id);
    }

    public function test_two_patches_create_two_revisions_and_last_edited_updates(): void
    {
        $owner = User::factory()->create(['user_type' => 'member']);
        $folder = Folder::query()->create([
            'user_id' => $owner->id,
            'name' => 'F',
            'parent_id' => null,
            'sort_order' => 0,
        ]);

        $this->jsonAs($owner, 'POST', '/api/notes', [
            'folder_id' => $folder->id,
            'title' => 'T0',
            'description' => 'D0',
        ])->assertCreated();

        $note = Note::query()->firstOrFail();
        $sid = (string) Str::uuid();
        $this->jsonAs($owner, 'POST', "/api/notes/{$note->id}/lock", ['session_id' => $sid])->assertOk();

        $this->jsonAs($owner, 'PATCH', "/api/notes/{$note->id}", [
            'title' => 'T1',
            'editor_session_id' => $sid,
        ])->assertOk();

        $this->jsonAs($owner, 'PATCH', "/api/notes/{$note->id}", [
            'description' => 'D1',
            'editor_session_id' => $sid,
        ])
            ->assertOk()
            ->assertJsonPath('note.last_edited_by.id', $owner->id);

        $this->assertSame(2, $note->revisions()->count());

        $revPayload = $this->jsonAs($owner, 'GET', "/api/notes/{$note->id}/revisions")
            ->assertOk()
            ->json('revisions');
        $revList = is_array($revPayload['data'] ?? null) ? $revPayload['data'] : (is_array($revPayload) ? $revPayload : []);
        $this->assertCount(2, $revList);
    }
}
