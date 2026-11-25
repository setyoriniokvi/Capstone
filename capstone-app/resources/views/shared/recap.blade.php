{{-- F18 — Rekap Penilaian (admin; dosen kelas: Rekap Nilai Kelas). Langkah kedua setelah Raw Data Penilaian: nilai per
     bentuk penilaian × CLO seperti sheet "Indikator Draft -> Igadis", sekaligus pengambilan data untuk iGadis (Ekspor iGadis).
     Atas tabel: [Kelas] (dosen kelas: menyaring langsung di browser; Ekspor iGadis aktif setelah kelas dipilih) · Cari · Sort (langsung di browser) dan jumlah tim lengkap.
     Tabel: NIM | Nama | Kelas | Nama Tim | bentuk penilaian × CLO (judul 4 baris: bentuk penilaian · CLO capstone · CLO iGadis
     · bobot %; isi = nilai 0–100 = Σ nilai indikator ÷ bobot kolom × 100, "-" bila bentuk penilaian itu belum lengkap) |
     NA (rata tengah). Penyesuaian Nilai pembimbing: NA = nilai disesuaikan (nilai sistem di bawahnya), kolom diskalakan proporsional.
     Bawah: "Tampilkan [10] data" (10 / 25 / 50 / 100 / Semua) + ‹ 1 2 3 › (Alpine "bulkApproval").
     Hanya tombol Ekspor iGadis; tanpa jumlah tim lengkap & daftar komponen yang belum. Indeks cukup di iGadis.
     Butuh $results, $columns, $exportRoute; opsional $classes + $class (filter kelas dosen kelas). --}}
@php
    $classes ??= null;
    $class ??= null;
    $needsClass = $classes !== null && $class === null;
    $exportBase = route($exportRoute, ['mode' => 'igadis']);
    $greenHead = 'bg-brand-tint [&_th]:border-rule [&_th]:py-2.5 [&_th]:text-sm [&_th]:font-semibold [&_th]:text-brand-deep';
    $groups = $columns->groupBy('component');
    $pct = fn ($w) => rtrim(rtrim(number_format($w, 2, ',', ''), '0'), ',');
    $num = fn ($v) => number_format((float) $v, 2, ',', '');
    $exporter = app(\App\Services\RecapExporter::class);
    $exportParams = array_filter(['mode' => 'igadis', 'kelas' => $class]);
    $pageTitle = $classes !== null ? 'Rekap Nilai Kelas' : 'Rekap Penilaian';
@endphp
<x-layouts.app :title="$pageTitle">
    <x-page-head :title="$pageTitle" :lede="$classes !== null
        ? 'Pilih kelas, lalu unduh nilainya dalam format template iGadis. Nilai per bentuk penilaian dan CLO dihitung otomatis dari penilaian final dosen dan peer review.'
        : null">
        <x-slot:actions>
            @if ($classes !== null)
                {{-- Dosen kelas: tautan mengikuti pilihan Kelas (data-filter-link), nonaktif sampai kelas dipilih --}}
                <a download href="{{ route($exportRoute, $exportParams) }}" data-filter-link="kelas" data-href="{{ $exportBase }}" class="btn btn-secondary {{ $needsClass ? 'pointer-events-none opacity-50' : '' }}"
                   @if ($needsClass) aria-disabled="true" @endif title="Pilih kelas, lalu unduh format template iGadis"><x-heroicon-o-arrow-down-tray class="h-5 w-5" />Ekspor iGadis</a>
            @else
                <a download href="{{ route($exportRoute, $exportParams) }}" class="btn btn-secondary" title="Format template iGadis; nilai yang belum lengkap dikosongkan"><x-heroicon-o-arrow-down-tray class="h-5 w-5" />Ekspor iGadis</a>
            @endif
        </x-slot:actions>
    </x-page-head>

    @error('export')<p class="mb-4 rounded-lg border border-bad-line bg-bad-tint px-4 py-3 text-sm text-bad-ink">{{ $message }}</p>@enderror

    <section x-data="bulkApproval" data-page-size="10" data-sort-scope @sorted="apply" class="panel mb-6 overflow-hidden" aria-label="Rekap penilaian per mahasiswa">
        {{-- [Kelas] · Cari · Sort --}}
        <div class="flex flex-wrap items-end justify-between gap-3 px-5 py-4 sm:px-6">
            @if ($classes !== null)
                {{-- Dosen kelas: pilih kelas (langsung disaring di browser; dapat mengekspor semua kelas) --}}
                <div class="w-full sm:w-56">
                    <label class="field-label" for="kelas-rekap">Kelas</label>
                    <select id="kelas-rekap" name="kelas" data-filter="kelas" class="input" @change="filter">
                        <option value="" @selected($class === null)>Semua kelas</option>
                        @foreach ($classes as $option)<option value="{{ $option }}" @selected($class === $option)>{{ $option }}</option>@endforeach
                    </select>
                </div>
            @endif
            <div class="min-w-[12rem] flex-1">
                <label class="field-label" for="cari-rekap">Cari</label>
                <div class="flex gap-2">
                    <input id="cari-rekap" type="search" class="input min-w-0 flex-1" placeholder="NIM, nama, kelas, tim" autocomplete="off" @input="search" @disabled($results->isEmpty())>
                    <x-sort-by id="urut-rekap" default="nim" :options="['nim' => 'NIM', 'nama' => 'Nama', 'kelas' => 'Kelas', 'tim' => 'Nama Tim', 'total' => 'NA']" />
                </div>
            </div>
        </div>
        @if ($results->isEmpty())
            <x-empty title="Belum ada tim yang dinilai" icon="table-cells">{{ $classes !== null ? 'Belum ada mahasiswa berkelas pada tim yang diterima.' : '' }}</x-empty>
        @else
            <x-scroll-pager>
                <table class="data-table border-t border-rule [&_td]:whitespace-nowrap">
                    <thead class="{{ $greenHead }}">
                        <tr>
                            @foreach (['NIM', 'Nama', 'Kelas', 'Nama Tim'] as $head)<th rowspan="4" class="align-bottom">{{ $head }}</th>@endforeach
                            @foreach ($groups as $component => $cols)
                                <th colspan="{{ $cols->count() }}" class="border-x !text-center">{{ config("capstone.components.$component.label", $component) }}</th>
                            @endforeach
                            <th rowspan="3" class="border-x !text-center !align-middle">NA</th>
                        </tr>
                        <tr>
                            @foreach ($columns as $col)<th class="border-x !text-center !text-caption">{{ $col['clo'] }}</th>@endforeach
                        </tr>
                        <tr>
                            @foreach ($columns as $col)<th class="code border-x !text-center !text-[0.6875rem] !font-medium">{{ $col['igadis'] ?: '-' }}</th>@endforeach
                        </tr>
                        <tr class="[&>th]:bg-warn-tint [&>th]:!text-ink">
                            @foreach ($columns as $col)<th class="border-x !text-center tabular !text-caption">{{ $pct($col['weight']) }}</th>@endforeach
                            <th class="border-x !text-center tabular !text-caption">{{ $pct($columns->sum('weight')) }}</th>
                        </tr>
                    </thead>
                    <tbody data-sort-list>
                        @foreach ($results as $result)
                            @foreach ($result['rows'] as $i => $row)
                                @php
                                    $student = $row['student'];
                                    $search = mb_strtolower(implode(' ', [$student->nim, $student->name, $student->class_name, $result['team']->name]));
                                    $adjusted = $row['adjusted'] ?? null;
                                @endphp
                                <tr data-team="{{ $student->id }}" data-search="{{ $search }}" data-sort-item data-sort-nim="{{ $student->nim }}" data-sort-nama="{{ $student->name }}"
                                    data-sort-kelas="{{ $student->class_name }}" data-filter-kelas="{{ $student->class_name }}" data-sort-tim="{{ $result['team']->name }}"
                                    data-sort-total="{{ $result['complete'] ? $row['total'] : '' }}">
                                    <td class="code">{{ $student->nim }}</td>
                                    <td>{{ $student->name }}</td>
                                    <td class="code">{{ $student->class_name ?? '-' }}</td>
                                    <td>
                                        {{ $result['team']->name }}
                                    </td>
                                    @foreach ($columns as $col)
                                        @php $cell = $exporter->cellValue($result, $row, $col); @endphp
                                        <td class="border-x border-rule text-center tabular {{ $cell ? 'text-ink' : 'text-muted' }}" @if ($cell && $cell['adjusted']) title="Diskalakan sesuai Penyesuaian Nilai" @endif>{{ $cell ? $num($cell['value']) : '-' }}</td>
                                    @endforeach
                                    <td class="border-x border-rule !text-center !align-middle tabular font-medium">
                                        @if ($result['complete'])
                                            {{ $num($row['total']) }}
                                            @if ($adjusted !== null)
                                                <p class="text-caption font-normal text-warn-ink" title="Penyesuaian Nilai dosen pembimbing">Disesuaikan · sistem {{ $num($row['system_total']) }}</p>
                                            @endif
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </x-scroll-pager>
            <p x-show="empty" x-cloak class="border-t border-rule px-5 py-5 text-sm text-muted sm:px-6">Tidak ada mahasiswa yang cocok dengan pencarian atau kelas.</p>
            <x-client-pager id="per-halaman-rekap" label="Halaman rekap penilaian" />
        @endif
    </section>
    <p class="text-caption text-muted">Nilai kolom = Σ nilai indikator pada bentuk penilaian dan CLO itu ÷ bobot kolom × 100 (rata-rata berbobot, 0–100). NA = Σ nilai kolom × bobot ÷ 100. Penyesuaian Nilai dosen pembimbing menggantikan NA, dan seluruh kolom mahasiswa itu diskalakan proporsional agar jumlahnya sama.</p>
</x-layouts.app>
