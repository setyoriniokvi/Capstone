<?php

namespace App\Http\Controllers;

use App\Models\SupportFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** F20 / F45 — mahasiswa & dosen melihat file pendukung. */
class SupportFileController extends Controller
{
    public function index(): View
    {
        return view('shared.files', [
            'groups' => SupportFile::query()->orderBy('category')->latest()->get()->groupBy('category'),
        ]);
    }

    public function download(SupportFile $file): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($file->path), 404);

        return Storage::disk('local')->download($file->path, $file->original_name);
    }
}
