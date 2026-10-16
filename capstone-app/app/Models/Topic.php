<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Topic extends Model
{
    protected $fillable = [
        'period_id', 'source', 'title', 'description', 'field', 'team_id', 'lecturer_id', 'quota',
        'status', 'review_notes', 'keyword_hits', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'keyword_hits' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lecturer_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(TopicApplication::class);
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('source', 'dosen')->where('status', 'diterima');
    }

    public function acceptedCount(): int
    {
        return $this->applications()->where('status', 'diterima')->count();
    }

    public function remainingQuota(): int
    {
        return max(0, $this->quota - $this->acceptedCount());
    }
}
