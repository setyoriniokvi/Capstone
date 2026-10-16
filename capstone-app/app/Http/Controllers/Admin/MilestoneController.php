<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Milestone;
use App\Models\Period;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** F2 — milestone & tenggat setiap tahapan. */
class MilestoneController extends Controller
{
    public function store(Request $request, Period $period): RedirectResponse
    {
        $period->milestones()->create($this->validated($request));

        return back()->with('status', 'Milestone ditambahkan.');
    }

    public function update(Request $request, Milestone $milestone): RedirectResponse
    {
        $milestone->update($this->validated($request));

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

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'kind' => ['required', Rule::in(array_keys(config('capstone.milestone_kinds')))],
            'document_type' => ['nullable', 'required_if:kind,dokumen', Rule::in(array_keys(config('capstone.document_types')))],
            'week_no' => ['nullable', 'integer', 'between:1,20'],
            'starts_at' => ['required', 'date'],
            'due_at' => ['required', 'date', 'after:starts_at'],
            'sort_order' => ['nullable', 'integer', 'between:0,999'],
        ], [
            'document_type.required_if' => 'Pilih jenis dokumen untuk milestone pengumpulan dokumen.',
            'due_at.after' => 'Tenggat harus setelah waktu mulai.',
        ]);
        $data['document_type'] = $data['kind'] === 'dokumen' ? $data['document_type'] : null;
        $data['sort_order'] ??= 0;

        return $data;
    }
}
