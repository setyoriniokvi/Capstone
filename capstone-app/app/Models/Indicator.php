<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Indicator extends Model
{
    use BelongsToPeriod;

    protected $fillable = ['clo_id', 'code', 'name', 'evidence', 'weight', 'scheme', 'document_types'];

    /** Tingkat rubrik yang dapat dipilih (1–5); interval maksimum = tingkat tertinggi yang berisi deskripsi rubrik. */
    public const MAX_LEVEL = 5;

    protected function casts(): array
    {
        return ['document_types' => 'array', 'weight' => 'float'];
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

    /**
     * Interval maksimum penilaian indikator = banyaknya poin rubrikasi (tingkat tertinggi yang terisi di Rubrik
     * Dokumen, mis. 4 poin → interval 1–4). Tanpa rubrik: 5.
     */
    public function maxInterval(): int
    {
        for ($level = self::MAX_LEVEL; $level >= 1; $level--) {
            if (filled($this->rubric?->describe($level))) {
                return $level;
            }
        }

        return self::MAX_LEVEL;
    }

    /** Poin rubrikasi [tingkat => deskripsi] dari 1 sampai interval maksimum (urut naik). */
    public function rubricPoints(): array
    {
        return collect(range(1, $this->maxInterval()))
            ->mapWithKeys(fn (int $level) => [$level => (string) $this->rubric?->describe($level)])
            ->all();
    }

    /** Label "Dokumen terkait" (Rubrik Dokumen; boleh lebih dari satu, hanya informasi). @return list<string> */
    public function documentTypeLabels(): array
    {
        return collect($this->document_types ?? [])->map(fn ($t) => config("capstone.document_types.{$t}", $t))->values()->all();
    }

    public function usesOralFactor(?string $component = null): bool
    {
        // Skema yang memakai Rubrik Lisan (aturan Faktor Lisan), dan per bentuk penilaian apakah rumusnya memakai
        // Faktor Lisan (kartu "Cara nilai dihitung").
        if ($component !== null && ! AssessmentComponent::formulaOf($component)['oral']) {
            return false;
        }

        return in_array($this->scheme, ScoreRule::oralSchemes(), true);
    }

    public function schemeLabel(): string
    {
        return config("capstone.schemes.{$this->scheme}.label", $this->scheme);
    }
}
