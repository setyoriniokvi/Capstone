<?php

namespace App\Models\Concerns;

use App\Models\Period;
use App\Support\PeriodContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Data master milik satu periode (kolom period_id). Query otomatis dibatasi ke periode konteks (PeriodContext) dan
 * baris baru otomatis diberi periode konteks. Baris tanpa periode (period_id NULL) adalah data master bawaan
 * (template) yang disalin ke periode baru.
 */
trait BelongsToPeriod
{
    public static function bootBelongsToPeriod(): void
    {
        static::addGlobalScope('period', function (Builder $query) {
            $id = PeriodContext::id();
            if ($id) {
                $query->where($query->qualifyColumn('period_id'), $id);
            } elseif (PeriodContext::strict()) {
                $query->whereRaw('1 = 0');
            }
        });

        static::creating(function (Model $model) {
            if (! $model->period_id && ($id = PeriodContext::id())) {
                $model->period_id = $id;
            }
        });
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    /** Query untuk periode tertentu, terlepas dari periode konteks. */
    public static function inPeriod(Period|int|null $period): Builder
    {
        $id = $period instanceof Period ? $period->id : $period;
        $query = static::query()->withoutGlobalScope('period');

        return $id ? $query->where('period_id', $id) : $query->whereNull('period_id');
    }
}
