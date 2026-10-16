<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Period;
use App\Services\Import\ImportService;
use App\Services\Import\SpreadsheetReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** F3 / F4 — impor Excel/CSV dengan pratinjau validasi per baris. */
class ImportController extends Controller
{
    private const LABELS = ['dosen' => 'Dosen', 'mahasiswa' => 'Mahasiswa', 'tim' => 'Tim'];

    public function template(string $type): StreamedResponse
    {
        $columns = ImportService::TEMPLATES[$type];
        $examples = [
            'dosen' => ['198001012010011001', 'Nama Dosen', 'ABC', 'dosen@gmail.com', '081234567890', 'SI', 'Enterprise Systems', 'pengusul;pembimbing;penguji'],
            'mahasiswa' => ['1020223000xx', 'Nama Mahasiswa', 'mahasiswa@gmail.com', 'SI', '081234567890', '120', 'MK001;MK002', 'Jawa Barat', 'Enterprise Systems'],
            'tim' => ['KP-SI-01', 'Nama Tim', '1020223000xx', '1020223000yy', '', 'SI'],
        ][$type];

        return response()->streamDownload(function () use ($columns, $examples) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $columns);
            fputcsv($out, $examples);
            fclose($out);
        }, "template-impor-{$type}.csv", ['Content-Type' => 'text/csv']);
    }

    public function preview(Request $request, string $type, SpreadsheetReader $reader, ImportService $importer): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:5120']], ['file.mimes' => 'Gunakan file .csv atau .xlsx.']);
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension()) === 'xlsx' ? 'xlsx' : 'csv';

        try {
            $rows = $reader->read($file->getRealPath(), $extension);
        } catch (\Throwable) {
            return back()->withErrors(['file' => 'File tidak dapat dibaca. Pastikan memakai template yang disediakan.']);
        }

        if ($rows === []) {
            return back()->withErrors(['file' => 'File tidak berisi data.']);
        }
        if ($missing = $reader->missingColumns($rows, ImportService::REQUIRED[$type])) {
            return back()->withErrors(['file' => 'Kolom wajib tidak ditemukan: '.implode(', ', $missing).'. Unduh template untuk format yang benar.']);
        }

        $token = Str::random(32);
        Cache::put("import:{$request->user()->id}:{$type}", ['token' => $token, 'rows' => $importer->preview($type, $rows, Period::active()), 'file' => $file->getClientOriginalName()], now()->addMinutes(30));

        return redirect()->route('admin.imports.show', $type);
    }

    public function show(Request $request, string $type): View|RedirectResponse
    {
        $data = Cache::get("import:{$request->user()->id}:{$type}");
        if (! $data) {
            return redirect()->back()->withErrors(['file' => 'Pratinjau impor kedaluwarsa. Unggah ulang file.']);
        }

        return view('admin.import-preview', ['type' => $type, 'label' => self::LABELS[$type], 'data' => $data]);
    }

    public function commit(Request $request, string $type, ImportService $importer): RedirectResponse
    {
        $key = "import:{$request->user()->id}:{$type}";
        $data = Cache::get($key);
        if (! $data || ! hash_equals($data['token'], (string) $request->input('token'))) {
            return redirect()->back()->withErrors(['file' => 'Pratinjau impor kedaluwarsa. Unggah ulang file.']);
        }

        // Validasi ulang saat commit agar data yang berubah sejak pratinjau tidak lolos.
        $fresh = $importer->preview($type, array_map(fn ($r) => ['line' => $r['line'], 'data' => $r['data']], $data['rows']), Period::active());
        $saved = $importer->commit($type, $fresh, Period::active());
        Cache::forget($key);
        ActivityLog::record("import_{$type}", null, ['saved' => $saved, 'file' => $data['file']]);

        $skipped = count($fresh) - $saved;
        $route = ['dosen' => 'admin.lecturers.index', 'mahasiswa' => 'admin.students.index', 'tim' => 'admin.teams.index'][$type];

        return redirect()->route($route)->with('status', "{$saved} baris berhasil diimpor".($skipped ? ", {$skipped} baris dilewati karena bermasalah." : '.'));
    }
}
