<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Services\DocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** F39 / F40 — pemantauan status sinkronisasi Google Drive. */
class DocumentController extends Controller
{
    public function index(Request $request, DocumentService $documents): View
    {
        $versions = DocumentVersion::query()
            ->with(['document.team', 'document.owner', 'document.milestone'])
            ->when($request->filled('status'), fn ($q) => $q->where('sync_status', $request->string('status')))
            ->latest('id')
            ->paginate(30)->withQueryString();

        return view('admin.documents', [
            'versions' => $versions,
            'driveEnabled' => $documents->driveEnabled(),
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
