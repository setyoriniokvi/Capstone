{{-- Tab "Log Bimbingan": jumlah tim bimbingan → satu tabel berisi log bimbingan semua tim bimbingan.
     Kolom: Tim (nama + bilah "X / min sesi minimal" log disetujui) · Jumlah Bimbingan · Tanggal Bimbingan · Catatan Bimbingan.
     Satu baris per sesi (urut tanggal); sel Tim & Jumlah digabung (rowspan) untuk semua sesi tim itu; tiap tim satu <tbody>.
     Sesi yang belum disetujui diberi lencana status; feedback dosen tampil kecil di bawah catatan.
     Di bawah judul: kolom cari (nama tim, tanggal, catatan, feedback) yang menyaring per tim di browser (Alpine "logSearch").
     Tiap tim punya jangkar #tim-{id} (tombol "Lihat detail" di Tim Bimbingan menggulir ke tim itu).
     Butuh $teams (approved_count), $logs, $minLogbooks. --}}
@php
    $statusLabel = ['menunggu' => 'Menunggu', 'disetujui' => 'Disetujui', 'revisi' => 'Ditolak'];
    $logsByTeam = $logs->groupBy('team_id');
@endphp
<section class="panel mb-6 flex items-center justify-between gap-4 px-5 py-5 sm:px-8" aria-label="Ringkasan">
    <p class="text-base font-medium text-ink">Jumlah Tim Bimbingan</p>
    <p class="tabular text-[2rem] font-bold leading-none tracking-tight text-brand">{{ $teams->count() }}</p>
</section>

<section x-data="logSearch" class="panel overflow-hidden" aria-labelledby="log-semua-judul">
    <div class="px-5 py-4 sm:px-6">
        <h2 id="log-semua-judul" class="text-lg font-semibold text-ink">History Bimbingan</h2>
    </div>
    <div class="border-t border-rule px-5 py-4 sm:px-6">
        <div class="relative">
            <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-faint" />
            <label for="cari-log" class="sr-only">Cari log bimbingan</label>
            <input id="cari-log" type="search" class="input pl-9" placeholder="Cari nama tim, tanggal, atau catatan" autocomplete="off" @input="search">
        </div>
    </div>

    {{-- Tabel dapat digeser ke samping di layar sempit (tombol < / > muncul bila ada sisi yang tersembunyi) --}}
    <x-scroll-pager class="border-t border-rule">
        <table class="data-table w-full min-w-[56rem] table-fixed [&_td]:px-4 [&_th]:px-4 [&_tbody]:border-b [&_tbody]:border-rule [&_tbody_tr:hover_td]:bg-transparent">
            <colgroup><col class="w-[22%]"><col class="w-[12%]"><col class="w-[16%]"><col class="w-[50%]"></colgroup>
            <thead class="bg-brand-tint [&>tr>th]:border-0 [&>tr>th]:py-3.5 [&>tr>th]:text-sm [&>tr>th]:font-semibold [&>tr>th]:text-brand-deep">
                <tr class="[&>th]:!text-center"><th>Tim</th><th>Jumlah Bimbingan</th><th>Tanggal bimbingan</th><th>Catatan Bimbingan</th></tr>
            </thead>
            @foreach ($teams as $team)
                @php
                    $teamLogs = $logsByTeam->get($team->id, collect());
                    $span = max(1, $teamLogs->count());
                    $search = \Illuminate\Support\Str::lower($team->name.' '.$teamLogs->map(fn ($log) => $log->session_date->translatedFormat('j F Y').' '.$log->session_date->translatedFormat('j M Y').' '.$log->session_date->format('d/m/Y').' '.$log->activity.' '.$log->feedback)->implode(' '));
                @endphp
                <tbody id="tim-{{ $team->id }}" data-search="{{ $search }}" class="scroll-mt-24">
                    @forelse ($teamLogs as $log)
                        <tr class="[&>td]:py-3 [&>td]:align-top">
                            @if ($loop->first)
                                <td rowspan="{{ $span }}" class="border-r border-rule">
                                    <p class="font-semibold text-ink">{{ $team->name }}</p>
                                    {{-- Minimal {{ $minLogbooks }} sesi disetujui; boleh lebih — bilah penuh setelah minimal tercapai --}}
                                    <x-progress-bar class="mt-2" :value="$team->approved_count" :max="$minLogbooks" label="Log disetujui {{ $team->name }}" />
                                    <p class="mt-1.5 text-caption text-muted"><span class="tabular font-semibold text-ink">{{ $team->approved_count }}</span> / {{ $minLogbooks }} sesi minimal</p>
                                </td>
                                <td rowspan="{{ $span }}" class="border-r border-rule text-center tabular font-semibold text-ink">{{ $teamLogs->count() }}</td>
                            @endif
                            {{-- Tanggal rata tengah; !pl-4: di baris ke-2 dst. sel ini menjadi sel pertama (Tim & Jumlah digabung) dan tidak boleh ikut padding sel pertama --}}
                            <td class="border-r border-rule !pl-4 text-center text-sm text-ink">
                                <span class="block whitespace-nowrap">{{ $log->session_date->translatedFormat('j M Y') }}</span>
                                @if ($log->status !== 'disetujui')<x-badge class="mt-1" :status="$log->status" :label="$statusLabel[$log->status] ?? ucfirst($log->status)" />@endif
                            </td>
                            <td class="text-sm text-ink">
                                <p class="whitespace-pre-line">{{ $log->activity }}</p>
                                @if ($log->notes)<p class="mt-1.5 whitespace-pre-line text-caption text-muted"><span class="font-medium">Notes:</span> {!! \App\Support\Format::linkify($log->notes) !!}</p>@endif
                                @if ($log->feedback)<p class="mt-1.5 whitespace-pre-line text-caption text-muted"><span class="font-medium">Feedback:</span> {{ $log->feedback }}</p>@endif
                            </td>
                        </tr>
                    @empty
                        <tr class="[&>td]:py-3 [&>td]:align-top">
                            <td class="border-r border-rule">
                                <p class="font-semibold text-ink">{{ $team->name }}</p>
                                <x-progress-bar class="mt-2" :value="0" :max="$minLogbooks" label="Log disetujui {{ $team->name }}" />
                                <p class="mt-1.5 text-caption text-muted"><span class="tabular font-semibold text-ink">0</span> / {{ $minLogbooks }} sesi minimal</p>
                            </td>
                            <td class="border-r border-rule text-center tabular font-semibold text-ink">0</td>
                            <td colspan="2" class="text-sm text-muted">Belum ada log bimbingan.</td>
                        </tr>
                    @endforelse
                </tbody>
            @endforeach
        </table>
    </x-scroll-pager>
    <p x-show="empty" x-cloak class="border-t border-rule px-5 py-5 text-sm text-muted sm:px-6">Tidak ada log bimbingan yang cocok.</p>
</section>
