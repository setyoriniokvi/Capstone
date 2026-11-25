<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Validation\MilestoneInput;
use App\Models\Document;
use App\Models\Milestone;
use App\Models\Period;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** F2 — milestone & deadline setiap tahapan. */
class MilestoneController extends Controller
{
    public function store(Request $request, Period $period): RedirectResponse
    {
        $period->milestones()->create(MilestoneInput::validate($request->all()));

        return back()->with('status', 'Milestone ditambahkan.');
    }

    public function update(Request $request, Milestone $milestone): RedirectResponse
    {
        $milestone->update(MilestoneInput::validate($request->all()));

        return back()->with('status', 'Milestone diperbarui.');
    }

    public function destroy(Milestone $milestone): RedirectResponse
    {
        if ($milestone->kind === 'dokumen' && Document::query()->where('milestone_id', $milestone->id)->exists()) {
            return back()->withErrors(['milestone' => 'Milestone ini sudah memiliki dokumen mahasiswa dan tidak dapat dihapus.']);
        }
        $milestone->delete();

        return back()->with('status', 'Milestone dihapus.');
    }
}
