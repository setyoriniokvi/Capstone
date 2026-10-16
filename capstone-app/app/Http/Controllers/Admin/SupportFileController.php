<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/** F17 — admin mengunggah file pendukung (panduan, slide sosialisasi, dsb.). */
class SupportFileController extends Controller
{
    public function index(): View
    {
        return view('admin.files', [
            'files' => SupportFile::query()->with('uploader')->orderBy('category')->latest()->get(),
            'categories' => SupportFile::query()->distinct()->orderBy('category')->pluck('category'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'category' => ['required', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:1000'],
            'file' => ['required', 'file', 'mimes:pdf,docx,pptx,xlsx,jpg,jpeg,png,zip', 'max:51200'],
        ]);
        $file = $request->file('file');

        SupportFile::create([
            'title' => $data['title'],
            'category' => trim($data['category']),
            'description' => $data['description'] ?? null,
            'path' => $file->store('support-files', 'local'),
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('status', 'File pendukung diunggah dan dapat dilihat mahasiswa serta dosen.');
    }

    public function update(Request $request, SupportFile $file): RedirectResponse
    {
        $file->update($request->validate([
            'title' => ['required', 'string', 'max:150'],
            'category' => ['required', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]));

        return back()->with('status', 'File pendukung diperbarui.');
    }

    public function destroy(SupportFile $file): RedirectResponse
    {
        Storage::disk('local')->delete($file->path);
        $file->delete();

        return back()->with('status', 'File pendukung dihapus.');
    }
}
