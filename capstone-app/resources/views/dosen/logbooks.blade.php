{{-- F46 — Approval Bimbingan (dosen pembimbing) dengan dua tab.
     "Pengajuan Log Bimbingan": log yang menunggu keputusan dari semua tim bimbingan.
     "Log Bimbingan": seluruh log per tim (dropdown tim + pencarian, Alpine "logTable"), tabel dapat digeser ke samping.
     Pada tab pengajuan: Dropdown di tengah judul menyaring per tim langsung di browser (Alpine "rowFilter").
     Setujui = konfirmasi; Tolak = popup feedback opsional (status tersimpan "revisi", tampil "Ditolak" di mahasiswa). --}}
@php
    $greenHead = 'bg-brand-tint [&>tr>th]:border-0 [&>tr>th]:py-3.5 [&>tr>th]:text-sm [&>tr>th]:font-semibold [&>tr>th]:text-brand-deep';
    $cells = '[&_td]:break-words [&_td]:px-3 [&_th]:px-3 [&_td:first-child]:pl-5 [&_th:first-child]:pl-5 [&_td:last-child]:pr-5 [&_th:last-child]:pr-5 sm:[&_td:first-child]:pl-6 sm:[&_th:first-child]:pl-6 sm:[&_td:last-child]:pr-6 sm:[&_th:last-child]:pr-6';
@endphp
<x-layouts.app title="Approval Bimbingan" section="Dosen · Approval bimbingan">
    <x-page-head title="Approval Bimbingan" lede="Setujui log bimbingan tim atau tolak dengan feedback. Setiap tim perlu minimal {{ $minLogbooks }} log disetujui sebelum presentasi akhir." />

    @error('logbook')<p class="mb-6 rounded-lg border border-bad-line bg-bad-tint px-4 py-3 text-sm text-bad-ink">{{ $message }}</p>@enderror

    <nav class="mb-6 inline-flex rounded-xl border border-rule bg-sunken p-1" aria-label="Tab log bimbingan">
        @foreach (['pengajuan' => 'Pengajuan Log Bimbingan', 'log' => 'Log Bimbingan'] as $key => $label)
            <a href="{{ route('dosen.logbooks.index', $key === 'log' ? ['tab' => 'log'] : []) }}" @if ($tab === $key) aria-current="page" @endif
               class="inline-flex min-h-[40px] items-center rounded-lg px-4 text-sm font-medium {{ $tab === $key ? 'bg-surface text-ink shadow-panel' : 'text-muted hover:text-ink' }}">{{ $label }}</a>
        @endforeach
    </nav>

    @if ($teams->isEmpty())
        <div class="panel"><x-empty title="Anda belum menjadi pembimbing tim mana pun" icon="clipboard-document-check" /></div>
    @elseif ($tab === 'log')
        @include('dosen._logbook-history')
    @else
        <section x-data="rowFilter" class="panel overflow-hidden" aria-labelledby="log-judul">
            {{-- Judul · filter tim · jumlah menunggu --}}
            <div class="grid gap-3 px-5 py-4 sm:grid-cols-[1fr_auto_1fr] sm:items-center sm:px-6">
                <h2 id="log-judul" class="text-lg font-semibold text-ink">Pengajuan Log Bimbingan</h2>
                <div>
                    <label for="filter-tim" class="sr-only">Filter tim</label>
                    <select id="filter-tim" class="input min-h-[40px] w-full py-1.5 text-sm sm:w-56" @change="change">
                        <option value="">Semua tim</option>
                        @foreach ($teams as $team)<option value="{{ $team->id }}">{{ $team->name }}</option>@endforeach
                    </select>
                </div>
                <p class="text-sm text-muted sm:text-right">Menunggu keputusan: <span class="tabular font-medium text-ink" x-text="count">{{ $pending->count() }}</span></p>
            </div>

            @if ($pending->isEmpty())
                <p class="border-t border-rule px-5 py-5 text-sm text-muted sm:px-6">Tidak ada log bimbingan yang menunggu.</p>
            @else
                <table class="data-table w-full table-fixed border-t border-rule {{ $cells }} [&_tbody_tr:hover_td]:bg-transparent">
                    <colgroup><col class="w-[17%]"><col class="w-[8%]"><col class="w-[14%]"><col class="w-[31%]"><col class="w-[14%]"><col class="w-[16%]"></colgroup>
                    <thead class="{{ $greenHead }}"><tr><th>Nama tim</th><th class="text-center">Sesi</th><th>Tanggal</th><th>Catatan bimbingan</th><th>Waktu pengajuan</th><th class="text-center">Aksi</th></tr></thead>
                    <tbody>
                        @foreach ($pending as $log)
                            <tr x-data="disclosure" data-team="{{ $log->team_id }}" class="[&>td]:py-4 [&>td]:align-top">
                                <td class="font-semibold text-ink">{{ $log->team->name }}</td>
                                <td class="text-center tabular text-ink">{{ $log->session_no }}</td>
                                <td class="text-sm text-ink">{{ $log->session_date->translatedFormat('j M Y') }}</td>
                                <td class="whitespace-pre-line text-sm text-ink">{{ $log->activity }}@if ($log->histories->where('event', 'revisi')->count())<span class="mt-1 block text-caption text-muted">Sudah {{ $log->histories->where('event', 'revisi')->count() }}× ditolak sebelumnya.</span>@endif</td>
                                <td class="text-sm text-ink">{{ $log->updated_at->translatedFormat('j M Y, H:i') }}</td>
                                <td class="!align-middle">
                                    <div class="mx-auto flex max-w-[8rem] flex-col gap-2">
                                        <form method="POST" action="{{ route('dosen.logbooks.review', $log) }}" data-confirm="Setujui log sesi {{ $log->session_no }} {{ $log->team->name }}?" data-confirm-label="Setujui" data-confirm-tone="positive">
                                            @csrf
                                            <input type="hidden" name="decision" value="approve">
                                            <button class="btn btn-primary btn-sm w-full">Setujui</button>
                                        </form>
                                        <button type="button" class="btn btn-secondary btn-sm w-full text-bad-ink" @click="toggle">Tolak</button>
                                    </div>

                                    <x-modal width="sm:max-w-lg">
                                        <x-slot:heading>Tolak log sesi {{ $log->session_no }} · {{ $log->team->name }}</x-slot:heading>
                                        <form method="POST" action="{{ route('dosen.logbooks.review', $log) }}" class="space-y-4">
                                            @csrf
                                            <input type="hidden" name="decision" value="reject">
                                            <div>
                                                <label class="field-label" for="fb-{{ $log->id }}">Feedback untuk tim <span class="font-normal text-muted">(opsional)</span></label>
                                                <textarea id="fb-{{ $log->id }}" name="notes" rows="4" class="input" maxlength="3000" placeholder="Bagian yang perlu diperbaiki"></textarea>
                                            </div>
                                            <div class="flex justify-end gap-3 border-t border-rule pt-4">
                                                <button type="button" class="btn btn-ghost" @click="close">Batal</button>
                                                <button class="btn btn-danger" data-once>Kirim</button>
                                            </div>
                                        </form>
                                    </x-modal>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p x-show="empty" x-cloak class="border-t border-rule px-5 py-5 text-sm text-muted sm:px-6">Tim ini tidak memiliki log yang menunggu.</p>
            @endif
        </section>
    @endif
</x-layouts.app>
