{{-- Log Registrasi Tim (keputusan diterima / ditolak), kartu pertama Master Data → Master Data Mahasiswa.
     Cari (?riwayat_q: tim, nama/NIM/email anggota) | Sort (?urut_riwayat / ?arah_riwayat) | Keputusan (?riwayat_status);
     "Tampilkan [5] data" (?riwayat_per) + ‹ › (?riwayat). "Detail tim" membuka modal admin/teams/_detail (ubah tim, dll.).
     Butuh $decided, $openTeam, $students, $specializations, $courses. --}}
@php
    $greenHead = 'bg-brand-tint [&>tr>th]:border-0 [&>tr>th]:py-3.5 [&>tr>th]:text-sm [&>tr>th]:font-semibold [&>tr>th]:text-brand-deep';
    $cells = '[&_td]:break-words [&_td]:px-3 [&_th]:px-3 [&_td:first-child]:pl-5 [&_th:first-child]:pl-5 [&_td:last-child]:pr-5 [&_th:last-child]:pr-5 sm:[&_td:first-child]:pl-6 sm:[&_th:first-child]:pl-6 sm:[&_td:last-child]:pr-6 sm:[&_th:last-child]:pr-6';
    $leaderChip = 'ml-1.5 rounded-md border border-rule bg-sunken px-2 py-0.5 text-caption font-medium text-muted inline-block whitespace-nowrap';
@endphp
<x-process-card card="riwayat" title="Log Registrasi Tim" :paginator="$decided" unit="tim"
                sort-name="urut_riwayat" dir-name="arah_riwayat" sort-id="urut-riwayat-tim" default="tim" dir="asc"
                :sorts="['tim' => 'Tim', 'terbaru' => 'Tanggal', 'kategori' => 'Kategori (Keputusan)']"
                :filters="['status' => ['label' => 'Keputusan', 'options' => ['diterima' => 'Diterima', 'ditolak' => 'Ditolak']]]"
                placeholder="Tim, nama / NIM / email anggota" empty="Belum ada registrasi tim yang diterima atau ditolak.">
    <table class="data-table w-full min-w-[64rem] table-fixed border-t border-rule {{ $cells }} [&_tbody_tr:hover_td]:bg-transparent">
        <colgroup><col class="w-[16%]"><col class="w-[22%]"><col class="w-[15%]"><col class="w-[11%]"><col class="w-[12%]"><col class="w-[12%]"><col class="w-[12%]"></colgroup>
        <thead class="{{ $greenHead }}"><tr><th>Tim</th><th>Anggota</th><th>Screening</th><th>Keputusan</th><th>Waktu</th><th>Catatan</th><th class="text-center">Aksi</th></tr></thead>
        <tbody>
            @foreach ($decided as $team)
                <tr class="[&>td]:align-top">
                    @include('admin.teams._row')
                    <td><x-badge class="!whitespace-normal" :status="$team->status" /></td>
                    <td class="text-sm text-ink">{{ $team->reviewed_at?->translatedFormat('j M Y, H:i') ?? '—' }}</td>
                    {{-- Catatan hanya ada pada penolakan (opsional); menerima tim tidak disertai catatan --}}
                    <td class="text-sm text-muted">{{ $team->status === 'ditolak' && filled($team->review_notes) ? $team->review_notes : '—' }}</td>
                    <td class="!align-middle text-center">@include('admin.teams._detail-button')</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</x-process-card>
