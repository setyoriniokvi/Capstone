<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReminderDispatch extends Model
{
    protected $fillable = [
        'dedupe_key', 'job_type', 'job_ref', 'lecturer_id', 'reminder_rule_id', 'trigger', 'channel', 'recipient',
        'payload', 'status', 'attempts', 'last_error', 'deadline_at', 'claimed_at', 'sent_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'deadline_at' => 'datetime',
            'claimed_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lecturer_id');
    }
}
