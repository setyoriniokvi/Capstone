<?php

namespace App\Observers;

use App\Models\Period;
use App\Services\PeriodMasterData;
use App\Support\PeriodContext;

/**
 * Efek samping siklus hidup periode. Dipisah dari model agar model tidak bergantung pada service:
 *   - periode baru menyalin data master (dari periode sumber Period::$masterSource, atau data master bawaan);
 *   - periode aktif berubah → konteks periode dosen & mahasiswa dihitung ulang.
 */
class PeriodObserver
{
    public function __construct(private readonly PeriodMasterData $masterData) {}

    public function created(Period $period): void
    {
        if ($period->masterSource !== false) {
            $this->masterData->copy($period->masterSource ?: null, $period->id);
        }
    }

    public function saved(Period $period): void
    {
        if ($period->wasChanged('is_active') || $period->wasRecentlyCreated) {
            PeriodContext::refresh(periodsChanged: true);
        }
    }
}
