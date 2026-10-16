<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Logbook extends Model
{
    protected $fillable = [
        'team_id', 'session_no', 'session_date', 'activity', 'responsible_person', 'instrument', 'data_collected',
        'output', 'status', 'feedback', 'reviewed_by', 'reviewed_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(LogbookHistory::class)->latest('id');
    }

    public function snapshot(string $event, ?User $actor = null): void
    {
        $this->histories()->create([
            'event' => $event,
            'actor_id' => $actor?->id,
            'snapshot' => $this->only(['session_no', 'session_date', 'activity', 'responsible_person', 'instrument', 'data_collected', 'output', 'status', 'feedback']),
        ]);
    }
}
