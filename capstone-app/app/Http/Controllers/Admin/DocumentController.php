<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Services\DocumentService;
use App\Services\Drive\DriveOAuth;
use App\Support\PerPage;
use App\Support\Sorting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** F39 / F40 — pemantauan status sinkronisasi Google Drive. */
class DocumentController extends Controller
{
    public function index(Request $request, DocumentService $documents, DriveOAuth $oauth): View
    {
        $query = DocumentVersion::query()
            ->with(['document.team', 'document.owner', 'document.milestone'])
            ->when($request->filled('status'), fn ($q) => $q->where('sync_status', $request->string('status')))
            // Cari: nama file, pemilik (nama / email), atau nama tim
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w->where('stored_name', 'like', $term)->orWhere('original_name', 'like', $term)
                    ->orWhereHas('document', fn ($d) => $d->where('owner_email', 'like', $term)
                        ->orWhereHas('owner', fn ($u) => $u->where('name', 'like', $term))
                        ->orWhereHas('team', fn ($t) => $t->where('name', 'like', $term))));
            });
        $via = fn (string $select) => fn ($q, $dir) => $q->orderBy(Document::query()->selectRaw($select)
            ->whereColumn('documents.id', 'document_versions.document_id')->limit(1), $dir);
        // Log: bawaan Diunggah turun (terbaru di atas)
        Sorting::apply($query, $request, [
            'diunggah' => 'created_at',
            'nama' => $via('(select name from users where users.id = documents.owner_id)'),
            'tim' => $via('(select name from teams where teams.id = documents.team_id)'),
            'kategori' => 'sync_status',
        ], 'diunggah', 'desc');
        $versions = $query->paginate(PerPage::from($request))->withQueryString();

        return view('admin.documents', [
            'versions' => $versions,
            'driveEnabled' => $documents->driveEnabled(),
            'documentService' => $documents,
            'driveAuth' => config('capstone.drive.auth'),
            'oauth' => $oauth,
            'counts' => DocumentVersion::query()->selectRaw('sync_status, count(*) as total')->groupBy('sync_status')->pluck('total', 'sync_status'),
        ]);
    }

    public function retry(DocumentVersion $version, DocumentService $documents): RedirectResponse
    {
        $documents->retry($version);

        return back()->with('status', 'Sinkronisasi dijadwalkan ulang.');
    }

    /** F38 — penghapusan permanen hanya oleh admin. */
    public function destroy(Request $request, Document $document, DocumentService $documents): RedirectResponse
    {
        $documents->delete($document, $request->user(), permanent: true);

        return back()->with('status', 'Dokumen dihapus permanen dari aplikasi dan Google Drive.');
    }
}
