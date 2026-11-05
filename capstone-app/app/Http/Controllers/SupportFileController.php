<?php

namespace App\Http\Controllers;

use App\Models\SupportFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** F20 / F45 — mahasiswa & dosen melihat file pendukung yang dibuka untuk role-nya (admin melihat semua). */
class SupportFileController extends Controller
{
    public function index(Request $request): View
    {
        return view('shared.files', [
            'groups' => SupportFile::query()->orderBy('category')->latest()->get()
                ->filter(fn (SupportFile $file) => $file->isVisibleTo($request->user()))->groupBy('category'),
        ]);
    }

    /** Tampilkan file di tab browser (Content-Disposition: inline) — hanya PDF & gambar. */
    public function show(Request $request, SupportFile $file): StreamedResponse
    {
        abort_unless($file->isVisibleTo($request->user()), 403, 'File ini tidak dibuka untuk peran Anda.');
        abort_unless($file->isPreviewable() && Storage::disk('local')->exists($file->path), 404);

        return Storage::disk('local')->response($file->path, $file->original_name, [
            'Content-Type' => SupportFile::PREVIEW_TYPES[$file->extension()],
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function download(Request $request, SupportFile $file): StreamedResponse
    {
        abort_unless($file->isVisibleTo($request->user()), 403, 'File ini tidak dibuka untuk peran Anda.');
        abort_unless(Storage::disk('local')->exists($file->path), 404);

        return Storage::disk('local')->download($file->path, $file->original_name);
    }
}
