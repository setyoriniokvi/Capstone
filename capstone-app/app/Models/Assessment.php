<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assessment extends Model
{
    protected $fillable = ['team_id', 'assessor_id', 'component', 'assessor_role', 'status', 'feedback', 'channel', 'submitted_at'];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime'];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessor_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(AssessmentScore::class);
    }

    public function componentLabel(): string
    {
        return config("capstone.components.{$this->component}.label", $this->component);
    }
}
