<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Indicator extends Model
{
    protected $fillable = ['clo_id', 'code', 'name', 'evidence', 'weight', 'scheme'];

    protected function casts(): array
    {
        return ['weight' => 'float'];
    }

    public function clo(): BelongsTo
    {
        return $this->belongsTo(Clo::class);
    }

    public function componentWeights(): HasMany
    {
        return $this->hasMany(IndicatorComponentWeight::class);
    }

    public function rubric(): HasOne
    {
        return $this->hasOne(DocumentRubric::class);
    }

    public function weightFor(string $component): float
    {
        return (float) ($this->componentWeights->firstWhere('component', $component)?->weight ?? 0);
    }

    public function usesOralFactor(): bool
    {
        return (bool) config("capstone.schemes.{$this->scheme}.oral", false);
    }

    public function schemeLabel(): string
    {
        return config("capstone.schemes.{$this->scheme}.label", $this->scheme);
    }
}
