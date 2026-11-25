<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Clo extends Model
{
    use BelongsToPeriod;

    protected $fillable = ['code', 'igadis_code', 'description', 'assessment_scheme', 'weight'];

    /**
     * Kode CLO iGadis (CLO SI, "PLOxx-CLOyy") bawaan per CLO capstone — sheet "Indikator Draft -> Igadis" / "Master CLO SI".
     * Disimpan utuh: nomor CLO-nya saja bisa kembar antar-PLO.
     */
    public const IGADIS_DEFAULTS = [
        'CLO1' => 'PLO01-CLO10',
        'CLO2' => 'PLO02-CLO05',
        'CLO3' => 'PLO02-CLO07',
        'CLO4' => 'PLO08-CLO08',
        'CLO5' => 'PLO03-CLO04',
        'CLO6' => 'PLO05-CLO05',
        'CLO7' => 'PLO07-CLO04',
        'CLO8' => 'PLO08-CLO07',
    ];

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
