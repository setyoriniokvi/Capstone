<?php

namespace App\Http\Controllers;

use App\Models\Period;
use App\Support\PeriodContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Dosen / mahasiswa yang terdaftar di beberapa periode aktif (prodi berjalan paralel) memilih periode kerja lewat
 * menu periode di sidebar. Pilihan disimpan per sesi, sehingga perangkat lain dengan akun yang sama tidak terpengaruh.
 */
class PeriodSwitchController extends Controller
{
    public function __invoke(Request $request, Period $period): RedirectResponse
    {
        $user = $request->user();
        abort_if($user->isAdmin(), 403);
        abort_unless(PeriodContext::periodsFor($user)->contains('id', $period->id), 403, 'Anda tidak terdaftar di periode ini.');

        PeriodContext::choose($period);

        return redirect()->route($user->homeRoute())->with('status', "Periode {$period->name} dibuka.");
    }
}
