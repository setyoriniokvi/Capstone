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
            'mahasiswa' => $document->team->members()->where('user_id', $user->id)->where('invite_status', 'diterima')->exists(),
            'dosen' => $document->team->lecturers()->where('lecturer_id', $user->id)->exists(),
            default => false,
        };
    }

    public function download(Request $request, DocumentVersion $version): StreamedResponse
    {
        $document = $version->document;
        abort_unless(self::canAccessDocument($request->user(), $document), 403, 'Anda tidak memiliki akses untuk dokumen ini.');
        abort_if($document->state === 'dihapus', 404);
        abort_unless(Storage::disk(DocumentService::DISK)->exists($version->local_path), 404);

        // Dibuka di tab browser (PDF/JPG tampil langsung), bukan unduhan paksa.
        return Storage::disk(DocumentService::DISK)->response($version->local_path, $version->stored_name);
    }

    /** Lampiran KHS / domisili registrasi tim. */
    public function attachment(Request $request, Team $team, TeamMember $member, string $kind): StreamedResponse
    {
        abort_unless($member->team_id === $team->id, 404);
        $user = $request->user();
        // Admin, atau sesama anggota tim (ketua mengunggah KHS seluruh anggota saat registrasi).
        $allowed = $user->isAdmin() || $team->members()->where('user_id', $user->id)->where('invite_status', 'diterima')->exists();
        abort_unless($allowed, 403, 'Anda tidak memiliki akses untuk lampiran ini.');

        $path = $kind === 'khs' ? $member->khs_path : $member->domicile_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        // Ditampilkan di tab browser (PDF/gambar), bukan diunduh paksa.
        return Storage::disk('local')->response($path, "{$kind}-{$member->user->nim}.".pathinfo($path, PATHINFO_EXTENSION));
    }
}
