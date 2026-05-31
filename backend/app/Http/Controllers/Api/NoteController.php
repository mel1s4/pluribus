<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\NoteLockRequest;
use App\Http\Requests\RevertNoteRequest;
use App\Http\Requests\StoreNoteCollaboratorRequest;
use App\Http\Requests\StoreNoteRequest;
use App\Http\Requests\UpdateNoteCollaboratorRequest;
use App\Http\Requests\UpdateNoteRequest;
use App\Http\Resources\NoteResource;
use App\Http\Resources\NoteRevisionDetailResource;
use App\Http\Resources\NoteRevisionSummaryResource;
use App\Models\Folder;
use App\Models\Note;
use App\Models\NoteEditLock;
use App\Models\NoteRevision;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class NoteController extends Controller
{
    private const LOCK_TTL_SECONDS = 90;

    public function index(Request $request): AnonymousResourceCollection
    {
        $userId = (int) $request->user()->id;
        $query = Note::query()
            ->visibleToUser($userId)
            ->with(['folder:id,name,icon_emoji,icon_bg_color,parent_id,sort_order,user_id'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        if ($request->filled('folder_id')) {
            $folderId = (int) $request->query('folder_id');
            Folder::query()->visibleToUser($userId)->whereKey($folderId)->firstOrFail();
            $query->where('folder_id', $folderId);
        }

        return NoteResource::collection($query->get());
    }

    public function store(StoreNoteRequest $request): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $request->validatedFolderVisibleTo($userId);
        $validated = $request->validated();

        $note = Note::query()->create([
            'folder_id' => (int) $validated['folder_id'],
            'author_id' => $userId,
            'last_edited_by_user_id' => $userId,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'content_markdown' => $validated['content_markdown'] ?? null,
            'tags' => $validated['tags'] ?? null,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);

        $note->load(['folder', 'lastEditedBy']);

        return response()->json([
            'note' => new NoteResource($note),
        ], 201);
    }

    public function show(Request $request, Note $note): JsonResponse
    {
        $this->authorize('view', $note);

        $note->loadCount('revisions');
        $note->load([
            'folder:id,name,icon_emoji,icon_bg_color,parent_id,sort_order,user_id',
            'lastEditedBy:id,name,avatar_path',
            'collaborators:id,name,avatar_path',
        ]);

        return response()->json([
            'note' => new NoteResource($note),
        ]);
    }

    public function update(UpdateNoteRequest $request, Note $note): JsonResponse
    {
        $this->authorize('update', $note);

        $user = $request->user();
        $validated = $request->validated();
        $sessionId = (string) $validated['editor_session_id'];
        unset($validated['editor_session_id']);

        $patchKeys = array_keys($validated);
        abort_unless(count($patchKeys) > 0, 422, 'No fields to update.');

        $this->assertValidEditLock($note, $user, $sessionId);

        DB::transaction(function () use ($note, $user, $validated): void {
            $note->refresh();
            if ($this->editorSnapshotFieldsChanged($note, $validated)) {
                NoteRevision::query()->create([
                    'note_id' => $note->id,
                    'edited_by_user_id' => (int) $user->id,
                    'title' => $note->title,
                    'description' => $note->description,
                    'tags' => $note->tags,
                    'content_markdown' => $note->content_markdown,
                    'created_at' => now(),
                ]);
                $note->last_edited_by_user_id = (int) $user->id;
            }

            $note->fill($validated);
            $note->save();
        });

        $note->load(['folder', 'lastEditedBy']);

        return response()->json([
            'note' => new NoteResource($note),
        ]);
    }

    public function destroy(Request $request, Note $note): JsonResponse
    {
        $this->authorize('delete', $note);
        $note->delete();

        return response()->json(['ok' => true]);
    }

    public function collaboratorsIndex(Request $request, Note $note): JsonResponse
    {
        $this->authorize('view', $note);
        $note->load('collaborators:id,name,avatar_path');

        $rows = $note->collaborators->map(fn (User $u): array => [
            'user' => [
                'id' => $u->id,
                'name' => $u->name,
            ],
            'permission' => (string) $u->pivot->permission,
        ]);

        return response()->json(['collaborators' => $rows->values()->all()]);
    }

    public function collaboratorsStore(StoreNoteCollaboratorRequest $request, Note $note): JsonResponse
    {
        $this->authorize('manageCollaborators', $note);

        if ($note->folder_id === null) {
            abort(422, 'Cannot add collaborators to a note without a folder.');
        }

        $targetUserId = (int) $request->validated('user_id');
        abort_if($targetUserId === (int) $note->author_id, 422, 'Author is not added as a collaborator.');

        $folderVisible = Folder::query()
            ->visibleToUser($targetUserId)
            ->whereKey((int) $note->folder_id)
            ->exists();
        abort_unless($folderVisible, 422, 'User cannot access this folder.');

        abort_if(
            $note->collaborators()->where('users.id', $targetUserId)->exists(),
            422,
            'User is already a collaborator.'
        );

        $permission = (string) $request->validated('permission');

        $note->collaborators()->attach($targetUserId, ['permission' => $permission]);

        return response()->json(['ok' => true], 201);
    }

    public function collaboratorsUpdate(UpdateNoteCollaboratorRequest $request, Note $note, User $user): JsonResponse
    {
        $this->authorize('manageCollaborators', $note);

        abort_unless(
            $note->collaborators()->where('users.id', $user->id)->exists(),
            404
        );

        $note->collaborators()->updateExistingPivot($user->id, [
            'permission' => $request->validated('permission'),
        ]);

        return response()->json(['ok' => true]);
    }

    public function collaboratorsDestroy(Request $request, Note $note, User $user): JsonResponse
    {
        $this->authorize('manageCollaborators', $note);

        $note->collaborators()->detach($user->id);

        return response()->json(['ok' => true]);
    }

    public function lockAcquire(NoteLockRequest $request, Note $note): JsonResponse
    {
        $this->authorize('update', $note);

        $user = $request->user();
        $sessionId = (string) $request->validated('session_id');

        $lockedUntil = DB::transaction(function () use ($note, $user, $sessionId) {
            Note::query()->whereKey($note->id)->lockForUpdate()->first();

            $lock = NoteEditLock::query()->where('note_id', $note->id)->lockForUpdate()->first();
            $now = now();

            if ($lock !== null && ! $lock->isExpired()) {
                if ((int) $lock->user_id !== (int) $user->id) {
                    throw new HttpResponseException(response()->json([
                        'message' => 'Note is locked by another user.',
                        'locked_by_user_id' => (int) $lock->user_id,
                        'locked_until' => $lock->locked_until->toIso8601String(),
                    ], 409));
                }
                if ($lock->session_id !== $sessionId) {
                    throw new HttpResponseException(response()->json([
                        'message' => 'Note is already being edited in another tab.',
                        'locked_by_user_id' => (int) $lock->user_id,
                        'locked_until' => $lock->locked_until->toIso8601String(),
                    ], 409));
                }
            }

            $until = $now->copy()->addSeconds(self::LOCK_TTL_SECONDS);

            NoteEditLock::query()->updateOrCreate(
                ['note_id' => $note->id],
                [
                    'user_id' => (int) $user->id,
                    'session_id' => $sessionId,
                    'locked_until' => $until,
                ]
            );

            return $until;
        });

        return response()->json([
            'locked_until' => $lockedUntil->toIso8601String(),
        ]);
    }

    public function lockRenew(NoteLockRequest $request, Note $note): JsonResponse
    {
        $this->authorize('update', $note);

        $user = $request->user();
        $sessionId = (string) $request->validated('session_id');

        $lockedUntil = DB::transaction(function () use ($note, $user, $sessionId) {
            Note::query()->whereKey($note->id)->lockForUpdate()->first();
            $lock = NoteEditLock::query()->where('note_id', $note->id)->lockForUpdate()->first();

            if ($lock === null || $lock->isExpired()) {
                abort(422, 'No active lock to renew.');
            }
            if ((int) $lock->user_id !== (int) $user->id || $lock->session_id !== $sessionId) {
                abort(422, 'Lock renewal denied.');
            }

            $until = now()->addSeconds(self::LOCK_TTL_SECONDS);
            $lock->locked_until = $until;
            $lock->save();

            return $until;
        });

        return response()->json([
            'locked_until' => $lockedUntil->toIso8601String(),
        ]);
    }

    public function lockRelease(NoteLockRequest $request, Note $note): JsonResponse
    {
        $this->authorize('update', $note);

        $user = $request->user();
        $sessionId = (string) $request->validated('session_id');

        $lock = NoteEditLock::query()->where('note_id', $note->id)->first();
        if ($lock !== null
            && (int) $lock->user_id === (int) $user->id
            && $lock->session_id === $sessionId) {
            $lock->delete();
        }

        return response()->json(['ok' => true]);
    }

    public function revisionsIndex(Request $request, Note $note): JsonResponse
    {
        $this->authorize('view', $note);

        $cursor = $request->query('cursor');
        $limit = min(50, max(1, (int) $request->query('limit', 30)));

        $query = NoteRevision::query()
            ->where('note_id', $note->id)
            ->with('editedBy:id,name,avatar_path')
            ->orderByDesc('id');

        if ($cursor !== null && ctype_digit((string) $cursor)) {
            $query->where('id', '<', (int) $cursor);
        }

        $rows = $query->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        if ($hasMore) {
            $rows = $rows->take($limit);
        }

        return response()->json([
            'revisions' => NoteRevisionSummaryResource::collection($rows),
            'next_cursor' => $hasMore ? $rows->last()?->id : null,
        ]);
    }

    public function revisionsShow(Request $request, Note $note, int $revision): JsonResponse
    {
        $this->authorize('view', $note);

        $row = NoteRevision::query()
            ->where('note_id', $note->id)
            ->whereKey($revision)
            ->with('editedBy:id,name,avatar_path')
            ->firstOrFail();

        return response()->json([
            'revision' => new NoteRevisionDetailResource($row),
        ]);
    }

    public function revert(RevertNoteRequest $request, Note $note): JsonResponse
    {
        $this->authorize('revert', $note);

        $user = $request->user();
        $sessionId = (string) $request->validated('editor_session_id');
        $revisionId = (int) $request->validated('revision_id');

        $this->assertValidEditLock($note, $user, $sessionId);

        $target = NoteRevision::query()
            ->where('note_id', $note->id)
            ->whereKey($revisionId)
            ->firstOrFail();

        DB::transaction(function () use ($note, $user, $target): void {
            $note->refresh();
            NoteRevision::query()->create([
                'note_id' => $note->id,
                'edited_by_user_id' => (int) $user->id,
                'title' => $note->title,
                'description' => $note->description,
                'tags' => $note->tags,
                'content_markdown' => $note->content_markdown,
                'created_at' => now(),
            ]);

            $note->title = $target->title;
            $note->description = $target->description;
            $note->tags = $target->tags;
            $note->content_markdown = $target->content_markdown;
            $note->last_edited_by_user_id = (int) $user->id;
            $note->save();
        });

        $note->load(['folder', 'lastEditedBy']);

        return response()->json([
            'note' => new NoteResource($note),
        ]);
    }

    private function assertValidEditLock(Note $note, User $user, string $sessionId): void
    {
        $lock = NoteEditLock::query()->where('note_id', $note->id)->first();
        if ($lock === null || $lock->isExpired()) {
            abort(422, 'Editor lock required or expired.');
        }
        if ((int) $lock->user_id !== (int) $user->id || $lock->session_id !== $sessionId) {
            abort(422, 'Editor lock invalid.');
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function editorSnapshotFieldsChanged(Note $note, array $validated): bool
    {
        if (array_key_exists('title', $validated) && (string) $validated['title'] !== (string) $note->title) {
            return true;
        }
        if (array_key_exists('description', $validated) && (string) ($validated['description'] ?? '') !== (string) ($note->description ?? '')) {
            return true;
        }
        if (array_key_exists('content_markdown', $validated) && (string) ($validated['content_markdown'] ?? '') !== (string) ($note->content_markdown ?? '')) {
            return true;
        }
        if (array_key_exists('tags', $validated) && json_encode($validated['tags'] ?? []) !== json_encode($note->tags ?? [])) {
            return true;
        }

        return false;
    }
}
