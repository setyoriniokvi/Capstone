<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Milestone;
use App\Models\Period;
use App\Models\Team;
use Illuminate\Validation\ValidationException;

/** Dasar controller mahasiswa: periode aktif, tim, dan jendela milestone. */
abstract class StudentController extends Controller
{
    protected function period(): Period
    {
        return Period::active() ?? abort(409, 'Belum ada periode Capstone yang aktif.');
    }

    protected function team(): ?Team
    {
        return auth()->user()->teamFor(Period::active());
    }

    protected function teamOrFail(bool $accepted = true): Team
    {
        $team = $this->team();
        if (! $team) {
            throw ValidationException::withMessages(['team' => 'Anda belum tergabung dalam tim.']);
        }
        if ($accepted && $team->status !== 'diterima') {
            throw ValidationException::withMessages(['team' => 'Registrasi tim belum diterima admin.']);
        }

        return $team;
    }

    /** Bila admin mengatur milestone untuk tahap ini, tahap hanya terbuka di rentang waktunya. */
    protected function ensureWindow(string $kind, string $label): void
    {
        $milestone = $this->period()->milestones()->where('kind', $kind)->orderBy('starts_at')->get();
        if ($milestone->isEmpty()) {
            return;
        }
        if (! $milestone->contains(fn (Milestone $m) => $m->isOpen())) {
            $first = $milestone->first();
            $message = now()->lt($first->starts_at)
                ? "{$label} dibuka mulai ".$first->starts_at->translatedFormat('j F Y H:i').'.'
                : "{$label} sudah ditutup pada ".$milestone->last()->due_at->translatedFormat('j F Y H:i').'.';

            throw ValidationException::withMessages(['window' => $message]);
        }
    }
}
