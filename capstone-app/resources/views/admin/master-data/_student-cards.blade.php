{{-- Master Data Mahasiswa: kartu log tiap proses mahasiswa (setelah Log Registrasi Tim). Masing-masing Cari | Sort | filter
     dan "Tampilkan [5] data" + ‹ › sendiri (x-process-card). Butuh $topicLog, $lecturerTopicApplicationLog, $logbookLog,
     $submissionLog, $peerLog, $peerTeams. --}}
@php
    $head = 'bg-brand-tint [&>tr>th]:border-0 [&>tr>th]:py-3.5 [&>tr>th]:text-sm [&>tr>th]:font-semibold [&>tr>th]:text-brand-deep';
    $table = 'data-table w-full border-t border-rule [&_td]:align-top [&_td]:text-sm [&_tbody_tr:hover_td]:bg-transparent';
    $date = fn ($d, $format = 'j M Y, H:i') => $d?->translatedFormat($format) ?? '—';
    $statuses = ['menunggu' => 'Menunggu', 'diterima' => 'Diterima', 'ditolak' => 'Ditolak', 'dibatalkan' => 'Dibatalkan'];
    $logbookStatus = ['menunggu' => 'Menunggu', 'disetujui' => 'Disetujui', 'revisi' => 'Ditolak (revisi)'];
    $reviewStatus = ['dikumpulkan' => 'Dikumpulkan', 'ditinjau' => 'Ditinjau', 'revisi' => 'Revisi', 'disetujui' => 'Disetujui'];
@endphp

{{-- Pengajuan topik: topik mandiri + lamaran ke topik dosen --}}
<x-process-card card="topik" title="Log Pengajuan Topik Mahasiswa" :paginator="$topicLog" unit="pengajuan" default="tanggal"
                :sorts="['tanggal' => 'Tanggal', 'tim' => 'Tim', 'judul' => 'Judul topik', 'kategori' => 'Kategori (Status)']"
                :filters="['jenis' => ['label' => 'Jenis topik', 'options' => ['mandiri' => 'Mandiri', 'dosen' => 'Dosen']], 'status' => ['label' => 'Status', 'options' => $statuses]]"
                placeholder="Tim, judul topik, dosen, catatan" empty="Belum ada pengajuan topik dari tim mahasiswa.">
    <table class="{{ $table }} min-w-[64rem]">
        <thead class="{{ $head }}"><tr><th>Diajukan</th><th>Tim</th><th>Judul topik</th><th>Jenis topik</th><th>Dosen</th><th>Status</th><th>Diputuskan</th><th>Catatan</th></tr></thead>
        <tbody>
            @foreach ($topicLog as $row)
                <tr>
                    <td class="whitespace-nowrap tabular">{{ $date($row['submitted']) }}</td>
                    <td class="font-medium text-ink">{{ $row['team'] }}</td>
                    <td class="min-w-[14rem] text-ink">{{ $row['title'] }}</td>
                    <td><span class="whitespace-nowrap rounded-md border border-rule bg-sunken px-2 py-0.5 text-caption font-medium text-muted">{{ $row['type'] === 'mandiri' ? 'Mandiri' : 'Dosen' }}</span></td>
                    <td>{{ $row['lecturer'] }}</td>
                    <td><x-badge :status="$row['status']" /></td>
                    <td class="whitespace-nowrap"><span class="tabular">{{ $date($row['decided']) }}</span>@if ($row['decider'])<p class="text-caption text-muted">{{ $row['decider'] }}</p>@endif</td>
                    <td class="min-w-[10rem] text-muted">{{ $row['notes'] ?: '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</x-process-card>

{{-- Keputusan dosen atas pengajuan tim ke topik dosen (diterima / ditolak) --}}
<x-process-card card="lamaran_topik" title="Log Pengajuan Topik Dosen oleh Mahasiswa" :paginator="$lecturerTopicApplicationLog" unit="keputusan" default="tanggal"
                :sorts="['tanggal' => 'Diputuskan', 'tim' => 'Tim', 'judul' => 'Judul topik', 'kategori' => 'Kategori (Keputusan)']"
                :filters="['status' => ['label' => 'Keputusan', 'options' => ['diterima' => 'Diterima', 'ditolak' => 'Ditolak']]]"
                placeholder="Tim, judul topik, dosen, catatan" empty="Belum ada keputusan dosen atas pengajuan topik dosen.">
    <table class="{{ $table }} min-w-[60rem]">
        <thead class="{{ $head }}"><tr><th>Diputuskan</th><th>Tim</th><th>Judul topik</th><th>Dosen pengusul</th><th>Diajukan</th><th>Keputusan</th><th>Catatan</th></tr></thead>
        <tbody>
            @foreach ($lecturerTopicApplicationLog as $row)
                <tr>
                    <td class="whitespace-nowrap"><span class="tabular">{{ $date($row['decided']) }}</span>@if ($row['decider'])<p class="text-caption text-muted">{{ $row['decider'] }}</p>@endif</td>
                    <td class="font-medium text-ink">{{ $row['team'] }}</td>
                    <td class="min-w-[14rem] text-ink">{{ $row['title'] }}</td>
                    <td>{{ $row['lecturer'] }}</td>
                    <td class="whitespace-nowrap tabular">{{ $date($row['submitted']) }}</td>
                    <td><x-badge :status="$row['status']" /></td>
                    <td class="min-w-[10rem] text-muted">{{ $row['notes'] ?: '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</x-process-card>

{{-- Log bimbingan yang diajukan: menunggu, disetujui, ditolak (revisi) --}}
<x-process-card card="bimbingan" title="Log Bimbingan" :paginator="$logbookLog" unit="sesi" default="tanggal"
                :sorts="['tanggal' => 'Tanggal sesi', 'tim' => 'Tim', 'sesi' => 'Sesi ke', 'kategori' => 'Kategori (Status)']"
                :filters="['status' => ['label' => 'Status', 'options' => $logbookStatus]]"
                placeholder="Tim, kegiatan, pengisi, pembimbing, feedback" empty="Belum ada log bimbingan yang diajukan.">
    <table class="{{ $table }} min-w-[64rem]">
        <thead class="{{ $head }}"><tr><th>Tanggal sesi</th><th>Tim</th><th>Sesi</th><th>Kegiatan</th><th>Diisi oleh</th><th>Status</th><th>Ditinjau</th><th>Feedback</th></tr></thead>
        <tbody>
            @foreach ($logbookLog as $row)
                <tr>
                    <td class="whitespace-nowrap tabular">{{ $date($row['date'], 'j M Y') }}</td>
                    <td class="font-medium text-ink">{{ $row['team'] }}</td>
                    <td class="tabular">{{ $row['session'] }}</td>
                    <td class="min-w-[14rem] text-ink"><p class="line-clamp-3" title="{{ $row['activity'] }}">{{ $row['activity'] }}</p></td>
                    <td>{{ $row['author'] ?? '—' }}</td>
                    <td><x-badge :status="$row['status']" :label="$logbookStatus[$row['status']] ?? null" /></td>
                    <td class="whitespace-nowrap"><span class="tabular">{{ $date($row['reviewed']) }}</span>@if ($row['reviewer'])<p class="text-caption text-muted">{{ $row['reviewer'] }}</p>@endif</td>
                    <td class="min-w-[10rem] text-muted">{{ $row['feedback'] ?: '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</x-process-card>

{{-- Submission: setiap file yang diunggah (termasuk unggah ulang / revisi) --}}
<x-process-card card="submission" title="Log Submission Mahasiswa" :paginator="$submissionLog" unit="file" default="tanggal"
                :sorts="['tanggal' => 'Waktu unggah', 'tim' => 'Tim', 'kategori' => 'Kategori (Dokumen)']"
                :filters="['dokumen' => ['label' => 'Dokumen', 'options' => config('capstone.document_types')], 'review' => ['label' => 'Status dokumen', 'options' => $reviewStatus]]"
                placeholder="Tim, dokumen, nama file, pengunggah" empty="Belum ada dokumen yang diunggah mahasiswa.">
    <table class="{{ $table }} min-w-[60rem]">
        <thead class="{{ $head }}"><tr><th>Waktu unggah</th><th>Tim</th><th>Dokumen</th><th>Revisi</th><th>Nama file</th><th>Diunggah oleh</th><th>Status dokumen</th></tr></thead>
        <tbody>
            @foreach ($submissionLog as $row)
                <tr>
                    <td class="whitespace-nowrap tabular">{{ $date($row['uploaded']) }}</td>
                    <td class="font-medium text-ink">{{ $row['team'] }}</td>
                    <td><p class="text-ink">{{ $row['typeLabel'] }}</p>@if ($row['milestone'])<p class="text-caption text-muted">{{ $row['milestone'] }}</p>@endif</td>
                    <td class="tabular">{{ $row['revision'] }}</td>
                    <td class="break-all code">{{ $row['file'] ?? '—' }}</td>
                    <td>{{ $row['uploader'] ?? '—' }}</td>
                    <td>@if ($row['review'])<x-badge :status="$row['review']" />@else — @endif</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</x-process-card>

{{-- Peer review: satu baris per pasangan penilai → yang dinilai --}}
<x-process-card card="peer" title="Log Peer Review" :paginator="$peerLog" unit="penilaian" default="tanggal"
                :sorts="['tanggal' => 'Waktu', 'tim' => 'Tim', 'nama' => 'Nama penilai', 'skor' => 'Rata-rata skor']"
                :filters="['tim' => ['label' => 'Tim', 'options' => $peerTeams->mapWithKeys(fn ($t) => [$t => $t])->all()], 'status' => ['label' => 'Status', 'options' => ['lengkap' => 'Lengkap', 'parsial' => 'Belum lengkap']]]"
                placeholder="Tim, nama / NIM penilai atau yang dinilai" empty="Belum ada peer review yang diisi mahasiswa.">
    <table class="{{ $table }} min-w-[56rem]">
        <thead class="{{ $head }}"><tr><th>Waktu</th><th>Tim</th><th>Penilai</th><th>Dinilai</th><th class="text-right">Rata-rata skor</th><th>Aspek dinilai</th><th>Status</th></tr></thead>
        <tbody>
            @foreach ($peerLog as $row)
                <tr>
                    <td class="whitespace-nowrap tabular">{{ $date($row['time']) }}</td>
                    <td class="font-medium text-ink">{{ $row['team'] }}</td>
                    <td><p class="text-ink">{{ $row['reviewer'] }}</p><p class="code text-caption text-muted">{{ $row['reviewerNim'] }}</p></td>
                    <td><p class="text-ink">{{ $row['reviewee'] }}</p><p class="code text-caption text-muted">{{ $row['revieweeNim'] }}</p></td>
                    <td class="text-right tabular font-medium">{{ number_format($row['average'], 2, ',', '') }}<span class="font-normal text-muted"> / 100</span></td>
                    <td class="tabular">{{ $row['aspects'] }} / {{ $row['expected'] }}</td>
                    <td><x-badge :status="$row['status']" :label="$row['status'] === 'lengkap' ? 'Lengkap' : 'Belum lengkap'" /></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</x-process-card>
