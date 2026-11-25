<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\DocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * F36 — setiap permintaan unduh divalidasi di backend:
 * mahasiswa hanya dokumen timnya sendiri, dosen hanya tim yang ditangani, admin semua.
 */
class DocumentFileController extends Controller
{
    public static function canAccessDocument(User $user, Document $document): bool
    {
        return match ($user->role) {
            'admin' => true,
            'admin_prodi' => $user->canOpenPeriod($document->team->period_id),
            'mahasiswa' => $document->team->members()->where('user_id', $user->id)->where('invite_status', 'diterima')->exists(),
            'dosen' => $document->team->lecturers()->where('lecturer_id', $user->id)->exists(),
            default => false,
        };
    }

    public function download(Request $request, DocumentVersion $version, DocumentService $documents): StreamedResponse
    {
        $document = $version->document;
        abort_unless(self::canAccessDocument($request->user(), $document), 403, 'Anda tidak memiliki akses untuk dokumen ini.');
        abort_if($document->state === 'dihapus', 404);
        $source = $documents->source($version);
        abort_if($source === null, 404, 'Revisi ini sudah digantikan revisi yang lebih baru.');

        // Dibuka di tab browser (PDF/JPG tampil langsung), bukan unduhan paksa.
        if (isset($source['local'])) {
            return Storage::disk(DocumentVersion::DISK)->response($source['local'], $version->stored_name);
        }

        // Sudah dipindah ke Google Drive: server mengambil isinya dan meneruskannya (pengguna tidak perlu akses Drive).
        try {
            $stream = $documents->driveStream($source['drive']);
        } catch (\Throwable $e) {
            report($e);
            abort(503, 'File tidak dapat diambil dari Google Drive saat ini. Coba lagi nanti.');
        }

        return response()->stream(function () use ($stream) {
            while (! $stream->eof()) {
                echo $stream->read(1024 * 1024);
                flush();
            }
        }, 200, [
            'Content-Type' => $version->mime ?: 'application/octet-stream',
            'Content-Disposition' => \Symfony\Component\HttpFoundation\HeaderUtils::makeDisposition('inline', $version->stored_name, Str::ascii($version->stored_name)),
        ]);
    }

    /** Lampiran KHS / domisili registrasi tim. */
    public function attachment(Request $request, Team $team, TeamMember $member, string $kind): StreamedResponse
    {
        abort_unless($member->team_id === $team->id, 404);
        $user = $request->user();
        // Admin, sesama anggota tim (ketua mengunggah KHS seluruh anggota saat registrasi), dosen pembimbing/penguji tim,
        // atau dosen pengusul topik yang dilamar tim (melihat detail tim di Approval Pengajuan Topik).
        $allowed = $user->isFacultyAdmin()
            || ($user->isProdiAdmin() && $user->canOpenPeriod($team->period_id))
            || $team->members()->where('user_id', $user->id)->where('invite_status', 'diterima')->exists()
            || $team->lecturers()->where('lecturer_id', $user->id)->exists()
            || $team->applications()->whereHas('topic', fn ($q) => $q->proposedBy($user->id))->exists();
        abort_unless($allowed, 403, 'Anda tidak memiliki akses untuk lampiran ini.');

        $path = $kind === 'khs' ? $member->khs_path : $member->domicile_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        // Ditampilkan di tab browser (PDF/gambar), bukan diunduh paksa.
        return Storage::disk('local')->response($path, "{$kind}-{$member->user->nim}.".pathinfo($path, PATHINFO_EXTENSION));
    }
}
