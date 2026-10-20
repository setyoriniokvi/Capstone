{{-- Tab "Log Bimbingan": jumlah tim bimbingan → cari + pilih tim → kartu info tim terpilih (nama, anggota, progres log)
     → tabel log tim tersebut. Penyaringan di browser (Alpine "logTable"). Butuh $teams (members.user, approved_count), $logs, $minLogbooks. --}}
@php
    $statusLabel = ['menunggu' => 'Menunggu', 'disetujui' => 'Disetujui', 'revisi' => 'Ditolak'];
@endphp
<section class="panel mb-6 flex items-center justify-between gap-4 px-5 py-5 sm:px-8" aria-label="Ringkasan">
    <p class="text-base font-medium text-ink">Jumlah Tim Bimbingan</p>
    <p class="tabular text-[2rem] font-bold leading-none tracking-tight text-brand">{{ $teams->count() }}</p>
</section>

<div x-data="logTable" data-team="{{ $teams->first()->id }}">
    {{-- Cari + pilih tim --}}
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="relative w-full min-w-0 sm:flex-1">
            <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-muted" />
            <label for="cari-log" class="sr-only">Cari log bimbingan</label>
            <input id="cari-log" type="search" class="input min-h-[48px] rounded-xl pl-12" placeholder="Cari tanggal atau feedback" @input="search">
        </div>
        <label for="pilih-tim" class="sr-only">Pilih tim</label>
        <select id="pilih-tim" class="input min-h-[48px] w-full rounded-xl sm:w-64 sm:shrink-0" @change="pick">
            @foreach ($teams as $team)<option value="{{ $team->id }}" @selected($loop->first)>{{ $team->name }}</option>@endforeach
        </select>
    </div>

    {{-- Info tim terpilih --}}
    @foreach ($teams as $team)
        {{-- Pembungkus tanpa kelas display agar atribut hidden benar-benar menyembunyikan --}}
        <div data-team-card="{{ $team->id }}" @unless ($loop->first) hidden @endunless>
        <section class="panel mb-4 grid gap-5 px-5 py-5 sm:grid-cols-3 sm:px-6" aria-label="Info {{ $team->name }}">
            <div class="flex flex-col justify-center">
                <p class="text-sm text-muted">Nama tim</p>
                <p class="mt-1 text-lg font-semibold text-ink">{{ $team->name }}</p>
            </div>
            <div>
                <p class="text-sm text-muted">Anggota</p>
                <ul class="mt-1 space-y-1 text-sm text-ink">
                    @foreach ($team->members->sortByDesc('is_leader') as $member)
                        <li class="flex flex-wrap items-center gap-2">{{ $member->user->name }}@if ($member->is_leader)<span class="rounded-md border border-rule bg-sunken px-2 py-0.5 text-caption font-medium text-muted">Ketua</span>@endif</li>
                    @endforeach
                </ul>
            </div>
            <div>
                <p class="text-sm text-muted">Progres log disetujui</p>
                {{-- Minimal {{ $minLogbooks }} sesi; boleh lebih — bilah penuh setelah minimal tercapai --}}
                <p class="mt-1 tabular text-lg font-semibold text-ink">{{ $team->approved_count }}<span class="text-sm font-normal text-muted"> / {{ $minLogbooks }} sesi minimal</span></p>
                <x-progress-bar class="mt-2" :value="$team->approved_count" :max="$minLogbooks" label="Log disetujui {{ $team->name }}" />
                @if ($team->approved_count >= $minLogbooks)<p class="mt-1.5 text-caption font-medium text-ok-ink">Syarat minimal terpenuhi</p>@endif
            </div>
        </section>
        </div>
    @endforeach

    <section class="panel overflow-hidden" aria-label="Log bimbingan tim">
        {{-- Tabel dapat digeser ke samping di layar sempit (bukan .table-wrap agar tidak diubah ke tampilan kartu) --}}
        <div class="overflow-x-auto">
            <table class="data-table w-full min-w-[60rem] table-fixed [&_td]:px-4 [&_td]:text-center [&_td]:!align-middle [&_th]:px-4 [&_th]:text-center [&_tbody_tr:hover_td]:bg-transparent">
                <colgroup><col class="w-[15%]"><col class="w-[15%]"><col class="w-[30%]"><col class="w-[14%]"><col class="w-[26%]"></colgroup>
                <thead class="bg-brand-tint [&>tr>th]:border-0 [&>tr>th]:py-3.5 [&>tr>th]:text-sm [&>tr>th]:font-semibold [&>tr>th]:text-brand-deep">
                    <tr><th>Tanggal input</th><th>Tanggal bimbingan</th><th>Catatan bimbingan</th><th>Status</th><th>Feedback</th></tr>
                </thead>
                <tbody>
                    @foreach ($logs as $log)
                        <tr data-team="{{ $log->team_id }}"
                            data-search="{{ \Illuminate\Support\Str::lower($log->feedback.' '.$log->session_date->translatedFormat('j F Y').' '.$log->session_date->translatedFormat('j M Y').' '.$log->created_at->translatedFormat('j F Y').' '.$log->created_at->translatedFormat('j M Y')) }}"
                            class="[&>td]:py-4">
                            <td class="text-sm text-ink">
                                <span class="block whitespace-nowrap">{{ $log->created_at->translatedFormat('j M Y') }}</span>
                                <span class="block tabular text-caption text-muted">{{ $log->created_at->format('H:i') }}</span>
                            </td>
                            <td class="whitespace-nowrap text-sm text-ink">{{ $log->session_date->translatedFormat('j M Y') }}</td>
                            <td class="whitespace-pre-line text-sm text-ink">{{ $log->activity }}</td>
                            <td><x-badge :status="$log->status" :label="$statusLabel[$log->status] ?? ucfirst($log->status)" /></td>
                            <td class="whitespace-pre-line text-sm {{ $log->feedback ? 'text-ink' : 'text-muted' }}">{{ $log->feedback ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p x-show="empty" x-cloak class="border-t border-rule px-5 py-5 text-sm text-muted sm:px-6">Tidak ada log bimbingan yang cocok untuk tim ini.</p>
    </section>
</div>
