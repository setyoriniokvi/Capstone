{{-- Master Data Dosen: kartu log tiap proses dosen. Masing-masing Cari | Sort | filter dan "Tampilkan [5] data" + ‹ ›
     sendiri (x-process-card). Butuh $assignmentLog, $lecturerTopicLog, $assessmentLog, $components. --}}
@php
    $head = 'bg-brand-tint [&>tr>th]:border-0 [&>tr>th]:py-3.5 [&>tr>th]:text-sm [&>tr>th]:font-semibold [&>tr>th]:text-brand-deep';
    $table = 'data-table w-full border-t border-rule [&_td]:align-top [&_td]:text-sm [&_tbody_tr:hover_td]:bg-transparent';
    $date = fn ($d, $format = 'j M Y, H:i') => $d?->translatedFormat($format) ?? '—';
    $chip = 'whitespace-nowrap rounded-md border border-rule bg-sunken px-2 py-0.5 text-caption font-medium text-muted';
    $num = fn ($v) => number_format((float) $v, 2, ',', '');
@endphp

{{-- Status dosen: penetapan pembimbing / penguji tiap tim --}}
<x-process-card card="status_dosen" title="Log Status Dosen" :paginator="$assignmentLog" unit="penetapan" default="tanggal"
                :sorts="['tanggal' => 'Tanggal', 'nama' => 'Nama dosen', 'tim' => 'Tim', 'kategori' => 'Kategori (Peran)']"
                :filters="['peran' => ['label' => 'Peran', 'options' => ['pembimbing' => 'Pembimbing', 'penguji' => 'Penguji']], 'sumber' => ['label' => 'Ditetapkan lewat', 'options' => ['sistem' => 'Sistem (topik dosen)', 'admin' => 'Plotting admin']]]"
                placeholder="Nama / kode dosen, tim" empty="Belum ada dosen yang ditetapkan sebagai pembimbing atau penguji.">
    <table class="{{ $table }} min-w-[52rem]">
        <thead class="{{ $head }}"><tr><th>Tanggal</th><th>Dosen</th><th>Tim</th><th>Peran</th><th>Ditetapkan lewat</th><th>Oleh</th></tr></thead>
        <tbody>
            @foreach ($assignmentLog as $row)
                <tr>
                    <td class="whitespace-nowrap tabular">{{ $date($row['time']) }}</td>
                    <td><p class="font-medium text-ink">{{ $row['lecturer'] }}</p>@if ($row['code'])<p class="code text-caption text-muted">{{ $row['code'] }}</p>@endif</td>
                    <td class="text-ink">{{ $row['team'] }}</td>
                    <td><span class="{{ $chip }}">{{ ucfirst($row['role']) }}</span></td>
                    <td>{{ $row['source'] === 'sistem' ? 'Sistem (topik dosen)' : 'Plotting admin' }}</td>
                    <td>{{ $row['assigner'] ?? ($row['source'] === 'sistem' ? 'Sistem' : '—') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</x-process-card>

{{-- Pengajuan topik dosen (bank topik) --}}
<x-process-card card="topik_dosen" title="Log Pengajuan Topik Dosen" :paginator="$lecturerTopicLog" unit="topik" default="tanggal"
                :sorts="['tanggal' => 'Tanggal', 'nama' => 'Nama dosen', 'judul' => 'Judul topik', 'kategori' => 'Kategori (Status)']"
                :filters="['status' => ['label' => 'Status', 'options' => ['menunggu' => 'Menunggu', 'diterima' => 'Diterima', 'ditolak' => 'Ditolak']]]"
                placeholder="Nama dosen, judul topik, catatan" empty="Belum ada topik yang diajukan dosen.">
    <table class="{{ $table }} min-w-[60rem]">
        <thead class="{{ $head }}"><tr><th>Diajukan</th><th>Dosen</th><th>Judul topik</th><th class="text-center">Kuota</th><th>Status</th><th>Diputuskan</th><th>Catatan</th></tr></thead>
        <tbody>
            @foreach ($lecturerTopicLog as $row)
                <tr>
                    <td class="whitespace-nowrap tabular">{{ $date($row['submitted']) }}</td>
                    <td><p class="font-medium text-ink">{{ $row['lecturer'] }}</p>@if ($row['proposers'])<p class="text-caption text-muted">bersama {{ implode(', ', $row['proposers']) }}</p>@endif</td>
                    <td class="min-w-[14rem] text-ink">{{ $row['title'] }}</td>
                    <td class="text-center tabular">{{ $row['accepted'] }} / {{ $row['quota'] }}</td>
                    <td><x-badge :status="$row['status']" /></td>
                    <td class="whitespace-nowrap"><span class="tabular">{{ $date($row['decided']) }}</span>@if ($row['decider'])<p class="text-caption text-muted">{{ $row['decider'] }}</p>@endif</td>
                    <td class="min-w-[10rem] text-muted">{{ $row['notes'] ?: '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</x-process-card>

{{-- Penilaian dosen per bentuk penilaian --}}
<x-process-card card="penilaian" title="Log Penilaian" :paginator="$assessmentLog" unit="penilaian" default="tanggal"
                :sorts="['tanggal' => 'Waktu', 'nama' => 'Nama dosen', 'tim' => 'Tim', 'nilai' => 'Nilai', 'kategori' => 'Kategori (Bentuk penilaian)']"
                :filters="['bentuk' => ['label' => 'Bentuk penilaian', 'options' => $components->all()], 'status' => ['label' => 'Status', 'options' => ['draft' => 'Draft', 'final' => 'Final']]]"
                placeholder="Nama dosen, tim, bentuk penilaian" empty="Belum ada penilaian yang diisi dosen.">
    <table class="{{ $table }} min-w-[60rem]">
        <thead class="{{ $head }}"><tr><th>Waktu</th><th>Dosen</th><th>Tim</th><th>Bentuk penilaian</th><th>Peran</th><th class="text-right">Nilai</th><th>Indikator terisi</th><th>Status</th></tr></thead>
        <tbody>
            @foreach ($assessmentLog as $row)
                <tr>
                    <td class="whitespace-nowrap tabular">{{ $date($row['time']) }}</td>
                    <td class="font-medium text-ink">{{ $row['lecturer'] }}</td>
                    <td class="text-ink">{{ $row['team'] }}</td>
                    <td>{{ $row['componentLabel'] }}@if ($row['channel'] === 'qr')<p class="text-caption text-muted">lewat QR</p>@endif</td>
                    <td><span class="{{ $chip }}">{{ ucfirst($row['role']) }}</span></td>
                    <td class="whitespace-nowrap text-right tabular font-medium">{{ $num($row['points']) }}<span class="font-normal text-muted"> / {{ $num($row['max']) }}</span></td>
                    <td class="tabular">{{ $row['scored'] }} / {{ $row['indicators'] }}</td>
                    <td><x-badge :status="$row['status']" /></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</x-process-card>
