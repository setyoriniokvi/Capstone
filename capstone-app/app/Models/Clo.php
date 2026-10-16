<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Clo extends Model
{
    protected $fillable = ['code', 'description', 'assessment_scheme', 'weight'];

    protected function casts(): array
    {
        return ['weight' => 'float'];
    }

    public function componentWeights(): HasMany
    {
        return $this->hasMany(CloComponentWeight::class);
    }

    public function indicators(): HasMany
    {
        return $this->hasMany(Indicator::class)->orderBy('code');
    }

    public function weightFor(string $component): float
    {
        return (float) ($this->componentWeights->firstWhere('component', $component)?->weight ?? 0);
    }
}
