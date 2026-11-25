{{-- Raw Data Penilaian (admin; langkah pertama sebelum Rekap Penilaian): rincian nilai mentah per mahasiswa, seluruh
     assessment sekaligus. Atas: tab selebar halaman Per Assessment/CLO | Per Indikator. Atas tabel: Cari · Sort (browser).
     Per Assessment/CLO: judul assessment → CLO (+ kode CLO iGadis) → "Nilai CLO | Lisan | Total CLOx", lalu Total Assessment.
       Lisan hanya bila ada indikator CLO itu yang dinilai lisan. Peer review: "Teman 1 | Teman 2 | … | Total CLOx".
     Per Indikator: judul CLO → kode indikator → kolom sesuai skema (Dokumen + Lisan: Dokumen | Lisan | Nilai; Dokumen:
       Dokumen | Nilai; Lisan: Lisan | Nilai; Peer Review: Teman 1 | Teman 2 | … | Nilai), lalu Total. Menggabungkan semua
       assessment yang sudah final (Dokumen & Lisan rata-rata, Nilai dan bobot dijumlah).
     Teman n = rata-rata nilai aspek dari teman ke-n (urut nama; nama teman di tooltip). "-" bila belum final / belum mengisi.
     Bawah: "Tampilkan [10] data" (10 / 25 / 50 / 100 / Semua) + ‹ 1 2 3 › (Alpine "bulkApproval").
     Butuh $view, $columns, $rows, $peerSlots; per indikator juga $groups, $weights. --}}
@php
    $head = 'bg-brand-tint [&_th]:border-rule [&_th]:py-3 [&_th]:text-sm [&_th]:font-semibold [&_th]:text-brand-deep';
    $num = fn ($v) => number_format((float) $v, 2, ',', '');
    $plain = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ',');
    $tab = fn (bool $on) => 'rounded-md px-3 py-2 text-center text-sm font-medium '.($on ? 'bg-brand-tint text-brand-deep' : 'text-muted hover:text-ink');
    $friends = range(1, $peerSlots);
    $cloName = fn (string $code) => preg_replace('/^CLO(\d+)$/', 'CLO $1', $code);
    // Per Assessment/CLO: kolom Lisan hanya untuk CLO yang dinilai lisan; peer review: kolom per teman
    $hasOral = fn (array $c, string $clo) => ! $c['peer'] && ($c['oral'][$clo] ?? false);
    $cloSpan = fn (array $c, string $clo) => $c['peer'] ? $peerSlots + 1 : ($hasOral($c, $clo) ? 3 : 2);
    $span = fn (array $c) => collect($c['clos'])->sum(fn ($clo) => $cloSpan($c, $clo)) + 1;
    // Per Indikator: kolom menurut skema penilaian
    $subs = fn ($indicator) => match ($indicator->scheme) {
        'dokumen' => ['doc', 'value'],
        'lisan' => ['oral', 'value'],
        'peer' => [...array_map(fn ($k) => "friend{$k}", $friends), 'value'],
        default => ['doc', 'oral', 'value'],
    };
    $subLabel = fn (string $sub) => match (true) {
        $sub === 'doc' => 'Dokumen', $sub === 'oral' => 'Lisan', $sub === 'value' => 'Nilai',
        default => 'Teman '.substr($sub, 6),
    };
    // Sel nilai teman ke-$k (1-based) dari baris mahasiswa
    $friendCell = function (array $row, int $k) use ($num) {
        $peer = $row['peers'][$k - 1] ?? null;

        return $peer ? '<span class="text-ink" title="'.e($peer['name']).'">'.$num($peer['score']).'</span>' : '<span class="text-muted">-</span>';
    };
@endphp
<x-layouts.app title="Raw Data Penilaian">
    <x-page-head title="Raw Data Penilaian" />

    {{-- Tampilan: Per Assessment/CLO | Per Indikator (tab selebar halaman) --}}
    <nav class="mb-4 grid grid-cols-2 gap-1 rounded-lg border border-rule bg-surface p-1" aria-label="Tampilan raw data">
        @foreach (\App\Http\Controllers\Admin\RawScoreController::VIEWS as $key => $label)
            <a href="{{ route('admin.raw-scores.index', ['tampilan' => $key]) }}" class="{{ $tab($view === $key) }}" @if ($view === $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>

    <section x-data="bulkApproval" data-page-size="10" data-sort-scope @sorted="apply" class="panel mb-6 overflow-hidden" aria-label="Raw data penilaian per mahasiswa">
        {{-- Label Cari (kiri) · jumlah mahasiswa (kanan, di atas Sort); lalu kolom cari + Sort --}}
        <div class="px-5 py-4 sm:px-6">
            <div class="mb-1.5 flex items-baseline justify-between gap-3">
                <label class="field-label !mb-0" for="cari-raw">Cari</label>
                <p class="text-sm"><span class="tabular font-medium">{{ $rows->count() }}</span><span class="text-muted"> mahasiswa</span></p>
            </div>
            <div class="flex gap-2">
                <input id="cari-raw" type="search" class="input min-w-0 flex-1" placeholder="NIM, nama, kelas, tim" autocomplete="off" @input="search" @disabled($rows->isEmpty())>
                <x-sort-by id="urut-raw" default="nim" :options="['nim' => 'NIM', 'nama' => 'Nama', 'kelas' => 'Kelas', 'tim' => 'Nama Tim']" />
            </div>
        </div>
        @if ($rows->isEmpty())
            <x-empty title="Belum ada tim yang diterima" icon="clipboard-document-list">Raw data muncul setelah ada tim diterima di periode ini.</x-empty>
        @else
            <x-scroll-pager>
                <table class="data-table border-t border-rule [&_td]:whitespace-nowrap [&_th]:whitespace-nowrap [&_td]:!text-center [&_td]:!align-middle [&_th]:!text-center [&_th]:!align-middle">
                    @if ($view === 'indikator')
                        {{-- Judul CLO → kode indikator → kolom sesuai skema --}}
                        <thead class="{{ $head }}">
                            <tr>
                                @foreach (['NIM', 'Nama', 'Kelas', 'Nama Tim'] as $title)<th rowspan="3">{{ $title }}</th>@endforeach
                                @foreach ($groups as $cloCode => $cloIndicators)
                                    @php $clo = $cloIndicators->first()->clo; @endphp
                                    <th colspan="{{ $cloIndicators->sum(fn ($i) => count($subs($i))) }}" class="border-x">
                                        {{ $cloName($cloCode) }}@if ($clo?->igadis_code)<span class="code block !text-caption font-normal text-muted">{{ $clo->igadis_code }}</span>@endif
                                    </th>
                                @endforeach
                                <th rowspan="3" class="border-r bg-brand/10">Total</th>
                            </tr>
                            <tr>
                                @foreach ($groups as $cloIndicators)
                                    @foreach ($cloIndicators as $indicator)
                                        <th colspan="{{ count($subs($indicator)) }}" class="border-x" title="{{ $indicator->name }}">
                                            <span class="code block">{{ $indicator->code }}</span>
                                            <span class="block !text-caption font-normal text-muted">bobot {{ $plain($weights[$indicator->id] ?? 0) }}</span>
                                        </th>
                                    @endforeach
                                @endforeach
                            </tr>
                            <tr>
                                @foreach ($groups as $cloIndicators)
                                    @foreach ($cloIndicators as $indicator)
                                        @foreach ($subs($indicator) as $i => $sub)<th class="{{ $i === 0 ? 'border-l' : '' }} !text-caption">{{ $subLabel($sub) }}</th>@endforeach
                                    @endforeach
                                @endforeach
                            </tr>
                        </thead>
                    @else
                        {{-- Judul assessment → CLO → kolom --}}
                        <thead class="{{ $head }}">
                            <tr>
                                @foreach (['NIM', 'Nama', 'Kelas', 'Nama Tim'] as $title)<th rowspan="3">{{ $title }}</th>@endforeach
                                @foreach ($columns as $item)
                                    <th colspan="{{ $span($item) }}" class="border-x">{{ $item['label'] }}</th>
                                @endforeach
                            </tr>
                            <tr>
                                @foreach ($columns as $item)
                                    @foreach ($item['clos'] as $i => $clo)
                                        @php $igadis = $item['igadis'][$clo] ?? null; @endphp
                                        <th colspan="{{ $cloSpan($item, $clo) }}" class="{{ $i === 0 ? 'border-l' : '' }} border-r !text-caption">
                                            {{ $cloName($clo) }}@if ($igadis)<span class="code block font-normal text-muted">{{ $igadis }}</span>@endif
                                        </th>
                                    @endforeach
                                    <th rowspan="2" class="border-r bg-brand/10 !text-caption">Total Assessment</th>
                                @endforeach
                            </tr>
                            <tr>
                                @foreach ($columns as $item)
                                    @foreach ($item['clos'] as $i => $clo)
                                        @if ($item['peer'])
                                            @foreach ($friends as $k)<th class="{{ $i === 0 && $k === 1 ? 'border-l' : '' }} !text-caption">Teman {{ $k }}</th>@endforeach
                                        @else
                                            <th class="{{ $i === 0 ? 'border-l' : '' }} !text-caption">Nilai CLO</th>
                                            @if ($hasOral($item, $clo))<th class="!text-caption">Lisan</th>@endif
                                        @endif
                                        <th class="!text-caption">Total {{ $cloName($clo) }}</th>
                                    @endforeach
                                @endforeach
                            </tr>
                        </thead>
                    @endif
                    <tbody data-sort-list>
                        @foreach ($rows as $row)
                            @php
                                $student = $row['student'];
                                $team = $row['team'];
                            @endphp
                            <tr data-team="{{ $student->id }}" data-search="{{ mb_strtolower(implode(' ', [$student->nim, $student->name, $student->class_name, $team->name])) }}"
                                data-sort-item data-sort-nim="{{ $student->nim }}" data-sort-nama="{{ $student->name }}" data-sort-kelas="{{ $student->class_name }}" data-sort-tim="{{ $team->name }}">
                                <td class="code">{{ $student->nim }}</td>
                                <td>{{ $student->name }}</td>
                                <td class="code">{{ $student->class_name ?? '-' }}</td>
                                <td>{{ $team->name }}</td>
                                @if ($view === 'indikator')
                                    @foreach ($groups as $cloIndicators)
                                        @foreach ($cloIndicators as $indicator)
                                            @php $c = $row['ready'] ? ($row['cells'][$indicator->id] ?? null) : null; @endphp
                                            @foreach ($subs($indicator) as $i => $sub)
                                                @php $isFriend = str_starts_with($sub, 'friend'); @endphp
                                                <td class="{{ $i === 0 ? 'border-l border-rule' : '' }} tabular {{ $isFriend ? '' : (! $c ? 'text-muted' : ($sub === 'value' ? 'font-medium text-ink' : 'text-ink')) }}">
                                                    @if ($isFriend){!! $friendCell($row, (int) substr($sub, 6)) !!}
                                                    @elseif (! $c) -
                                                    @elseif ($sub === 'doc'){{ $plain($c['doc']) }}@if ($c['level'] !== null)<span class="text-caption text-muted"> ({{ $c['level'] }})</span>@endif
                                                    @elseif ($sub === 'oral'){{ $c['oral'] !== null ? $num($c['oral']) : '-' }}
                                                    @else{{ $num($c['value']) }}
                                                    @endif
                                                </td>
                                            @endforeach
                                        @endforeach
                                    @endforeach
                                    <td class="border-r border-rule bg-brand/5 tabular {{ $row['ready'] ? 'font-semibold text-ink' : 'text-muted' }}">{{ $row['ready'] ? $num($row['total']) : '-' }}</td>
                                @else
                                    @foreach ($columns as $key => $item)
                                        @php $cell = $row['components'][$key] ?? ['ready' => false, 'clos' => [], 'total' => 0]; @endphp
                                        @foreach ($item['clos'] as $i => $clo)
                                            @php $c = $cell['clos'][$clo] ?? null; @endphp
                                            @if ($item['peer'])
                                                @foreach ($friends as $k)<td class="{{ $i === 0 && $k === 1 ? 'border-l border-rule' : '' }} tabular">{!! $friendCell($row, $k) !!}</td>@endforeach
                                            @else
                                                <td class="{{ $i === 0 ? 'border-l border-rule' : '' }} tabular {{ $cell['ready'] ? 'text-ink' : 'text-muted' }}">{{ $cell['ready'] ? $num($c['base'] ?? 0) : '-' }}</td>
                                                @if ($hasOral($item, $clo))<td class="tabular {{ $cell['ready'] ? 'text-ink' : 'text-muted' }}">{{ $cell['ready'] && isset($c['lisan']) ? $num($c['lisan']) : '-' }}</td>@endif
                                            @endif
                                            <td class="tabular {{ $cell['ready'] ? 'font-medium text-ink' : 'text-muted' }}">{{ $cell['ready'] ? $num($c['total'] ?? 0) : '-' }}</td>
                                        @endforeach
                                        <td class="border-r border-rule bg-brand/5 tabular {{ $cell['ready'] ? 'font-semibold text-ink' : 'text-muted' }}">{{ $cell['ready'] ? $num($cell['total']) : '-' }}</td>
                                    @endforeach
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-scroll-pager>
            <p x-show="empty" x-cloak class="border-t border-rule px-5 py-5 text-sm text-muted sm:px-6">Tidak ada mahasiswa yang cocok dengan pencarian.</p>
            <x-client-pager id="per-halaman-raw" label="Halaman raw data penilaian" />
        @endif
    </section>
    @if ($view === 'indikator')
        <p class="text-caption text-muted">Dokumen = nilai level Rubrik Dokumen (5 = 95, 4 = 80, 3 = 70, 2 = 50, 1 = 40, 0 = 0; tingkat dalam kurung). Lisan = Faktor Lisan dari Rubrik Lisan (per tim). Nilai = Dokumen ÷ 100 × bobot × Lisan, digabung dari semua assessment yang sudah final (Dokumen & Lisan rata-rata, Nilai dan bobot dijumlah). Peer review: Teman n = rata-rata nilai (0 / 50 / 65 / 75 / 90) dari teman ke-n; Nilai = (rata-rata semua teman ÷ 100) × bobot.</p>
    @else
        <p class="text-caption text-muted">Nilai CLO = jumlah nilai indikator CLO itu sebelum faktor lisan (kode CLO iGadis di bawah judul CLO). Lisan = faktor lisan efektif (hanya CLO yang dinilai lisan). Total CLO = Nilai CLO × Lisan. Total Assessment = jumlah Total CLO pada assessment itu. Peer review: Teman n = rata-rata nilai (0 / 50 / 65 / 75 / 90) dari teman ke-n; Total = (rata-rata semua teman ÷ 100) × bobot.</p>
    @endif
</x-layouts.app>
