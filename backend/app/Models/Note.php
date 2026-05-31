<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Note extends Model
{
    public const PERMISSION_VIEW = 'view';

    public const PERMISSION_EDIT = 'edit';

    /** @var list<string> */
    public const COLLABORATOR_PERMISSIONS = [
        self::PERMISSION_VIEW,
        self::PERMISSION_EDIT,
    ];

    /** @var list<string> */
    protected $fillable = [
        'folder_id',
        'author_id',
        'last_edited_by_user_id',
        'title',
        'description',
        'content_markdown',
        'tags',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tags' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Folder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'folder_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function lastEditedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_edited_by_user_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function collaborators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'note_collaborators')
            ->withPivot(['permission'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<NoteRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(NoteRevision::class);
    }

    /**
     * @return HasOne<NoteEditLock, $this>
     */
    public function editLock(): HasOne
    {
        return $this->hasOne(NoteEditLock::class);
    }

    public function scopeVisibleToUser(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $q) use ($userId): void {
            $q->where(function (Builder $inner) use ($userId): void {
                $inner->whereNotNull('folder_id')
                    ->whereHas('folder', fn (Builder $fq) => $fq->visibleToUser($userId));
            })->orWhere(function (Builder $inner) use ($userId): void {
                $inner->whereNull('folder_id')
                    ->where('author_id', $userId);
            });
        });
    }

    public function isAuthor(User $user): bool
    {
        return (int) $this->author_id === (int) $user->id;
    }

    public function isFolderOwner(User $user): bool
    {
        if ($this->folder_id === null) {
            return false;
        }

        if ($this->relationLoaded('folder') && $this->folder !== null) {
            return (int) $this->folder->user_id === (int) $user->id;
        }

        return Folder::query()
            ->whereKey((int) $this->folder_id)
            ->where('user_id', $user->id)
            ->exists();
    }

    public function collaboratorPermissionFor(int $userId): ?string
    {
        $permission = DB::table('note_collaborators')
            ->where('note_id', $this->id)
            ->where('user_id', $userId)
            ->value('permission');

        return $permission !== null ? (string) $permission : null;
    }

    public function userCanEdit(User $user): bool
    {
        if ($this->isAuthor($user)) {
            return true;
        }
        if ($this->isFolderOwner($user)) {
            return true;
        }

        return $this->collaboratorPermissionFor((int) $user->id) === self::PERMISSION_EDIT;
    }

    public function userCanManageCollaborators(User $user): bool
    {
        return $this->isAuthor($user) || $this->isFolderOwner($user);
    }
}
