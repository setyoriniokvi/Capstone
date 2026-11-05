<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * F17 — admin mengunggah file pendukung (panduan, slide sosialisasi, dsb.): kartu daftar file (role yang dapat melihat,
 * Unduh · Lihat · Hapus · Ubah) dan "Tambah File" (modal: judul, kategori, deskripsi, role akses melihat, file).
 * Ubah memakai isian yang sama; file baru opsional (kosong = file lama tetap dipakai).
 */
class SupportFileController extends Controller
{
    public function index(): View
    {
        return view('admin.files', [
            'files' => SupportFile::query()->with('uploader')->orderBy('category')->latest()->get(),
            'categories' => SupportFile::query()->distinct()->orderBy('category')->pluck('category'),
            'roles' => SupportFile::VIEWER_ROLES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, true);
        $file = $request->file('file');

        SupportFile::create([
            'title' => $data['title'],
            'category' => trim($data['category']),
            'description' => $data['description'] ?? null,
            'visible_to' => array_values($data['visible_to']),
            'path' => $file->store('support-files', 'local'),
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        $who = collect($data['visible_to'])->map(fn ($r) => strtolower(SupportFile::VIEWER_ROLES[$r]))->implode(' dan ');

        return back()->with('status', "File pendukung diunggah dan dapat dilihat {$who}.");
    }

    public function update(Request $request, SupportFile $file): RedirectResponse
    {
        $data = $this->validated($request, false);
        $attributes = [
            'title' => $data['title'],
            'category' => trim($data['category']),
            'description' => $data['description'] ?? null,
            'visible_to' => array_values($data['visible_to']),
        ];
        if ($upload = $request->file('file')) {
            Storage::disk('local')->delete($file->path);
            $attributes += [
                'path' => $upload->store('support-files', 'local'),
                'original_name' => $upload->getClientOriginalName(),
                'mime' => $upload->getMimeType() ?? 'application/octet-stream',
                'size' => $upload->getSize(),
            ];
        }
        $file->update($attributes);

        return back()->with('status', "File pendukung “{$file->title}” diperbarui.");
    }

    public function destroy(SupportFile $file): RedirectResponse
    {
        Storage::disk('local')->delete($file->path);
        $file->delete();

        return back()->with('status', 'File pendukung dihapus.');
    }

    /** Judul, kategori, deskripsi, role akses melihat (minimal satu), dan file (wajib saat tambah, opsional saat ubah). */
    private function validated(Request $request, bool $fileRequired): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'category' => ['required', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:1000'],
            'visible_to' => ['required', 'array', 'min:1'],
            'visible_to.*' => ['string', 'distinct', Rule::in(array_keys(SupportFile::VIEWER_ROLES))],
            'file' => [$fileRequired ? 'required' : 'nullable', 'file', 'mimes:pdf,docx,pptx,xlsx,jpg,jpeg,png,zip', 'max:51200'],
        ], ['visible_to.required' => 'Pilih minimal satu role yang dapat melihat file.']);
    }
}
