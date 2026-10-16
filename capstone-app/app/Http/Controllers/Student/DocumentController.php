<?php

namespace App\Http\Controllers\Student;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Milestone;
use App\Services\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** F26 / F33–F41 — dokumen milestone milik mahasiswa yang sedang login. */
class DocumentController extends StudentController
{
    public function index(Request $request, DocumentService $documents): View
    {
        $user = $request->user();
        $period = $this->period();
        $team = $this->team();
        $milestones = $period->milestones()->where('kind', 'dokumen')->orderBy('due_at')->get();

        $mine = $team ? Document::query()->where('team_id', $team->id)->where('owner_id', $user->id)
            ->with(['versions.uploader', 'currentVersion', 'milestone'])->get()->keyBy('milestone_id') : collect();
        // Dokumen anggota lain hanya ditampilkan sebagai metadata, tanpa akses unduh (F36).
        $teammates = $team ? Document::query()->where('team_id', $team->id)->where('owner_id', '!=', $user->id)
            ->where('state', 'aktif')->with('owner:id,name')->get()->groupBy('milestone_id') : collect();

        return view('mahasiswa.documents', [
            'team' => $team,
            'milestones' => $milestones,
            'mine' => $mine,
            'teammates' => $teammates,
            'gate' => $this->gate($team),
            'driveEnabled' => $documents->driveEnabled(),
            'approvedLogbooks' => $team?->approvedLogbookCount() ?? 0,
            'minLogbooks' => config('capstone.min_approved_logbooks'),
            'idempotencyKey' => (string) Str::uuid(),
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
            'file' => ['required', 'file', 'mimes:'.implode(',', config('capstone.upload.document_mimes')), 'max:'.config('capstone.upload.document_max_kb')],
            'idempotency_key' => ['required', 'uuid'],
        ], [
            'file.mimes' => 'Format file harus PDF, DOCX, PPTX, atau JPG.',
            'file.max' => 'Ukuran file maksimal '.(config('capstone.upload.document_max_kb') / 1024).' MB.',
        ]);

        $version = $documents->upload($team, $milestone, $request->user(), $request->file('file'), $request->input('idempotency_key'));
        $message = $version->revision_no > 1 ? "Revisi {$version->revision_no} diunggah." : 'Dokumen diunggah.';
        $message .= $documents->driveEnabled() ? ' Sinkronisasi ke Google Drive berjalan di latar belakang.' : '';

        if ($request->expectsJson()) {
            session()->flash('status', $message);

            return response()->json(['message' => $message, 'revision' => $version->revision_no]);
        }

        return back()->with('status', $message);
    }

    public function destroy(Request $request, Document $document, DocumentService $documents): RedirectResponse
    {
        abort_unless($document->owner_id === $request->user()->id, 403, 'Anda hanya dapat menghapus dokumen milik sendiri.');
        $documents->delete($document, $request->user());

        return back()->with('status', 'Dokumen dipindahkan ke Trash.');
    }

    public function retry(Request $request, DocumentVersion $version, DocumentService $documents): RedirectResponse
    {
        abort_unless($version->document->owner_id === $request->user()->id, 403);
        $documents->retry($version);

        return back()->with('status', 'Sinkronisasi dicoba ulang.');
    }

    /** PRD 8.2 — topik harus berstatus diterima sebelum unggah dokumen. */
    private function gate($team): ?string
    {
        return match (true) {
            ! $team => 'Anda belum tergabung dalam tim.',
            $team->status !== 'diterima' => 'Registrasi tim belum diterima admin.',
            ! $team->final_topic_id => 'Unggah dokumen dibuka setelah topik tim disetujui.',
            default => null,
        };
    }
}
