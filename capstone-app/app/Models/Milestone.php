<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Milestone extends Model
{
    protected $fillable = ['period_id', 'name', 'description', 'kind', 'document_type', 'week_no', 'starts_at', 'due_at', 'sort_order'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'due_at' => 'datetime',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function isOpen(): bool
    {
        return now()->between($this->starts_at, $this->due_at);
    }

    public function hasStarted(): bool
    {
        return now()->greaterThanOrEqualTo($this->starts_at);
    }

    public function isPast(): bool
    {
        return now()->greaterThan($this->due_at);
    }

    public function kindLabel(): string
    {
        return config("capstone.milestone_kinds.{$this->kind}", $this->kind);
    }
}
