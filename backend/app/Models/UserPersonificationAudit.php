<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPersonificationAudit extends Model
{
    public const ACTION_STARTED = 'started';

    public const ACTION_STOPPED = 'stopped';

    public const ACTION_EXPIRED = 'expired';

    public const ACTION_EXPIRED_IDLE = 'expired_idle';

    public const ACTION_FORCED_LOGOUT = 'forced_logout';

    /** @var list<string> */
    protected $fillable = [
        'actor_user_id',
        'target_user_id',
        'action',
        'reason',
        'ticket_reference',
        'ip',
        'user_agent',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
