<?php

namespace App\Http\Controllers\Student;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Milestone;
use App\Models\Team;
use App\Services\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * F26 / F33–F41 — dokumen milestone tim. Pengumpulan bersifat per tim: bila salah satu anggota
 * sudah mengunggah, milestone tersebut selesai untuk seluruh tim.
 */
class DocumentController extends StudentController
{
    /** Pilihan rentang tampilan: semua milestone, atau tenggat dalam 7 / 30 hari ke depan. */
    public const RANGES = ['semua' => 'Semua', '7' => '7 hari ke depan', '30' => '30 hari ke depan'];

    public function index(Request $request, DocumentService $documents): View
    {
        $period = $this->period();
        $team = $this->team();
        $range = array_key_exists((string) $request->query('rentang'), self::RANGES) ? (string) $request->query('rentang') : 'semua';

        $milestones = $period->milestones()->where('kind', 'dokumen')->orderBy('due_at')->get()
            ->when($range !== 'semua', fn ($c) => $c->filter(fn (Milestone $m) => $m->due_at->between(now(), now()->addDays((int) $range))))
            ->values();

        // Dokumen aktif tim per milestone (satu per milestone).
        $submissions = $team ? Document::query()->where('team_id', $team->id)->where('state', 'aktif')
            ->with(['versions.uploader:id,name', 'feedbackBy:id,name'])->latest('updated_at')->get()->unique('milestone_id')->keyBy('milestone_id') : collect();

        return view('mahasiswa.documents', [
            'team' => $team,
            'range' => $range,
            'groups' => $milestones->groupBy(fn (Milestone $m) => $m->due_at->toDateString()),
            'submissions' => $submissions,
            'gate' => $this->gate($team),
            'driveEnabled' => $documents->driveEnabled(),
            'approvedLogbooks' => $team?->approvedLogbookCount() ?? 0,
            'minLogbooks' => config('capstone.min_approved_logbooks'),
        ]);
    }

    public function store(Request $request, Milestone $milestone, DocumentService $documents): JsonResponse|RedirectResponse
    {
        $team = $this->teamOrFail();
        abort_unless($milestone->period_id === $team->period_id && $milestone->kind === 'dokumen', 404);

        if ($error = $this->gate($team)) {
            throw ValidationException::withMessages(['file' => $error]);
        }
        if ($milestone->document_type === 'materi_presentasi' && $team->approvedLogbookCount() < config('capstone.min_approved_logbooks')) {
            throw ValidationException::withMessages(['file' => 'Minimal '.config('capstone.min_approved_logbooks').' logbook harus disetujui sebelum presentasi akhir.']);
        }

        $request->validate([
            'file' => ['required', 'file', 'mimes:'.implode(',', $milestone->uploadMimes()), 'max:'.config('capstone.upload.document_max_kb')],
            'idempotency_key' => ['required', 'uuid'],
        ], [
            'file.mimes' => 'Format file harus '.$milestone->fileTypesLabel().'.',
            'file.max' => 'Ukuran file maksimal '.(config('capstone.upload.document_max_kb') / 1024).' MB.',
        ]);

        $version = $documents->upload($team, $milestone, $request->user(), $request->file('file'), $request->input('idempotency_key'));
        $message = $version->revision_no > 1 ? 'File pengumpulan diperbarui.' : 'Dokumen tim dikumpulkan.';
        $message .= $documents->driveEnabled() ? ' Sinkronisasi ke Google Drive berjalan di latar belakang.' : '';

        if ($request->expectsJson()) {
            session()->flash('status', $message);

            return response()->json(['message' => $message, 'revision' => $version->revision_no]);
        }

        return back()->with('status', $message);
    }

    public function destroy(Request $request, Document $document, DocumentService $documents): RedirectResponse
    {
        $this->authorizeTeam($document);
        $documents->delete($document, $request->user());

        return back()->with('status', 'Pengumpulan dihapus (file dipindahkan ke Trash).');
    }

    public function retry(Request $request, DocumentVersion $version, DocumentService $documents): RedirectResponse
    {
        $this->authorizeTeam($version->document);
        $documents->retry($version);

        return back()->with('status', 'Sinkronisasi dicoba ulang.');
    }

    /** Seluruh anggota tim boleh mengelola pengumpulan timnya. */
    private function authorizeTeam(Document $document): void
    {
        abort_unless($document->team_id === $this->team()?->id, 403, 'Dokumen ini bukan milik tim Anda.');
    }

    /** PRD 8.2 — topik harus berstatus diterima sebelum unggah dokumen. */
    private function gate(?Team $team): ?string
    {
        return $team ? $team->documentUploadBlocker() : 'Anda belum tergabung dalam tim.';
    }
}
