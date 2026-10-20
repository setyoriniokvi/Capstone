{{-- F43 — Approval Pengajuan Topik (dosen): kuota topik milik dosen → kartu "Pengajuan Topik" berisi pengajuan aktif
     (Terima / Tolak dengan alasan opsional) dan, dipisah garis, riwayat keputusan 3 per halaman.
     Semua tabel pas selebar layar (bukan .table-wrap, tanpa geser ke samping). --}}
@php
    // Judul kolom hijau hanya untuk tabel di bawah judul kartu ("Pengajuan Topik").
    $greenHead = 'bg-brand-tint [&>tr>th]:border-0 [&>tr>th]:py-3.5 [&>tr>th]:text-sm [&>tr>th]:font-semibold [&>tr>th]:text-brand-deep';
    $cells = '[&_td]:break-words [&_td]:px-3 [&_th]:px-3 [&_td:first-child]:pl-5 [&_th:first-child]:pl-5 [&_td:last-child]:pr-5 [&_th:last-child]:pr-5 sm:[&_td:first-child]:pl-6 sm:[&_th:first-child]:pl-6 sm:[&_td:last-child]:pr-6 sm:[&_th:last-child]:pr-6';
    $topicStatus = ['diterima' => ['diterima', 'Dipublikasikan'], 'menunggu' => ['menunggu', 'Menunggu admin'], 'ditolak' => ['ditolak', 'Ditolak admin']];
    // Portofolio selalu jadi tautan: isian tanpa skema diberi https://
    $portfolioHref = fn (string $value) => preg_match('#^https?://#i', $value) ? $value : 'https://'.ltrim($value, '/');
@endphp
<x-layouts.app title="Approval Pengajuan Topik" section="Dosen · Approval pengajuan topik">
    <x-page-head title="Approval Pengajuan Topik" lede="Menerima tim menjadikan Anda dosen pembimbing tim tersebut secara otomatis. Kuota tidak dapat dilampaui." />

    {{-- Kuota topik milik dosen --}}
    <section class="panel mb-6 overflow-hidden" aria-label="Kuota topik saya">
        @if ($topics->isEmpty())
            <x-empty title="Anda belum memiliki topik" icon="light-bulb" />
        @else
            <table class="data-table w-full table-fixed {{ $cells }} [&_tbody_tr:hover_td]:bg-transparent">
                <colgroup><col class="w-[46%]"><col class="w-[16%]"><col class="w-[17%]"><col class="w-[21%]"></colgroup>
                <thead><tr><th>Topik Saya</th><th class="text-center">Tim / kuota</th><th>Ketersediaan</th><th>Status</th></tr></thead>
                <tbody>
                    @foreach ($topics as $topic)
                        @php
                            $remaining = max(0, $topic->quota - $topic->accepted_count);
                            [$st, $stLabel] = $topicStatus[$topic->status] ?? [$topic->status, ucfirst($topic->status)];
                        @endphp
                        <tr class="[&>td]:py-4">
                            <td class="font-semibold leading-snug text-ink">{{ $topic->title }}</td>
                            <td class="text-center tabular text-ink">{{ $topic->accepted_count }} / {{ $topic->quota }}</td>
                            <td>
                                <span class="inline-flex items-center gap-2 text-sm font-medium {{ $remaining ? 'text-ok-ink' : 'text-bad-ink' }}">
                                    <span class="h-2 w-2 shrink-0 rounded-full {{ $remaining ? 'bg-accent' : 'bg-bad' }}" aria-hidden="true"></span>{{ $remaining ? 'Sisa '.$remaining : 'Penuh' }}
                                </span>
                            </td>
                            <td><x-badge class="!whitespace-normal" :status="$st" :label="$stLabel" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    {{-- Pengajuan Topik: pengajuan aktif, lalu riwayat keputusan (satu kartu, dipisah garis) --}}
    <section class="panel overflow-hidden" aria-labelledby="pelamar-judul">
        <div class="flex items-center justify-between gap-3 px-5 py-4 sm:px-6">
            <h2 id="pelamar-judul" class="text-lg font-semibold text-ink">Pengajuan Topik</h2>
            <span class="text-sm text-muted">Menunggu keputusan: <span class="tabular font-medium text-ink">{{ $pending->count() }}</span></span>
        </div>

        @if ($pending->isEmpty())
            <p class="border-t border-rule px-5 py-5 text-sm text-muted sm:px-6">Tidak ada pengajuan topik yang menunggu.</p>
        @else
            <table class="data-table w-full table-fixed border-t border-rule {{ $cells }} [&_tbody_tr:hover_td]:bg-transparent">
                <colgroup><col class="w-[18%]"><col class="w-[24%]"><col class="w-[18%]"><col class="w-[12%]"><col class="w-[14%]"><col class="w-[14%]"></colgroup>
                <thead class="{{ $greenHead }}"><tr><th>Nama tim</th><th>Topik yang diajukan</th><th>Anggota</th><th>Portofolio</th><th>Waktu pengajuan</th><th class="text-center">Aksi</th></tr></thead>
                <tbody>
                    @foreach ($pending as $app)
                        @php $remaining = max(0, $app->topic->quota - $app->topic->accepted_count); @endphp
                        <tr x-data="disclosure" class="[&>td]:py-4 [&>td]:align-top">
                            <td class="font-semibold text-ink">{{ $app->team->name }}</td>
                            <td class="text-sm leading-snug text-ink">{{ $app->topic->title }}</td>
                            <td class="text-sm">
                                <ul class="space-y-0.5 text-ink">
                                    @foreach ($app->team->members->where('invite_status', 'diterima') as $m)
                                        <li>{{ $m->user->name }}</li>
                                    @endforeach
                                </ul>
                            </td>
                            <td>
                                @if ($app->portfolio_url)
                                    <a href="{{ $portfolioHref($app->portfolio_url) }}" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-sm" title="{{ $app->portfolio_url }}">
                                        <x-heroicon-o-arrow-top-right-on-square class="h-4 w-4" /> Lihat
                                    </a>
                                @else
                                    <span class="text-sm text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-sm text-ink">{{ $app->created_at->translatedFormat('j M Y, H:i') }}</td>
                            <td class="!align-middle">
                                <div class="mx-auto flex max-w-[8rem] flex-col gap-2">
                                    <form method="POST" action="{{ route('dosen.applications.decide', $app) }}" data-confirm="Terima {{ $app->team->name }}? Anda otomatis menjadi dosen pembimbing tim ini." data-confirm-label="Terima" data-confirm-tone="positive">
                                        @csrf
                                        <input type="hidden" name="decision" value="approve">
                                        <button class="btn btn-primary btn-sm w-full disabled:cursor-not-allowed disabled:opacity-50" @disabled($remaining === 0) @if ($remaining === 0) title="Kuota topik ini sudah penuh" @endif>Terima</button>
                                    </form>
                                    <button type="button" class="btn btn-secondary btn-sm w-full text-bad-ink" @click="toggle">Tolak</button>
                                </div>

                                <x-modal width="sm:max-w-lg">
                                    <x-slot:heading>Tolak {{ $app->team->name }}</x-slot:heading>
                                    <form method="POST" action="{{ route('dosen.applications.decide', $app) }}" class="space-y-4">
                                        @csrf
                                        <input type="hidden" name="decision" value="reject">
                                        <div>
                                            <label class="field-label" for="alasan-{{ $app->id }}">Alasan Penolakan <span class="font-normal text-muted">(opsional)</span></label>
                                            <textarea id="alasan-{{ $app->id }}" name="notes" rows="4" class="input" maxlength="2000"></textarea>
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
        @endif

        {{-- Riwayat keputusan: 3 terbaru per halaman --}}
        <div class="border-t-2 border-rule">
            <h3 class="px-5 pb-2 pt-5 text-base font-semibold text-ink sm:px-6">Riwayat</h3>
            @if ($decided->isEmpty())
                <p class="px-5 pb-5 text-sm text-muted sm:px-6">Belum ada keputusan.</p>
            @else
                <table class="data-table w-full table-fixed {{ $cells }} [&_tbody_tr:hover_td]:bg-transparent">
                    <colgroup><col class="w-[20%]"><col class="w-[30%]"><col class="w-[14%]"><col class="w-[16%]"><col class="w-[20%]"></colgroup>
                    <thead class="{{ $greenHead }}"><tr><th>Tim</th><th>Topik</th><th>Keputusan</th><th>Waktu</th><th>Catatan</th></tr></thead>
                    <tbody>
                        @foreach ($decided as $app)
                            <tr class="[&>td]:align-top">
                                <td class="font-medium text-ink">{{ $app->team->name }}</td>
                                <td class="text-sm text-ink">{{ $app->topic->title }}</td>
                                <td><x-badge class="!whitespace-normal" :status="$app->status" /></td>
                                <td class="text-sm text-ink">{{ $app->decided_at?->translatedFormat('j M Y, H:i') }}</td>
                                {{-- Catatan hanya ada pada penolakan (alasan opsional); menerima tim tidak disertai catatan --}}
                                <td class="text-sm text-muted">{{ $app->status === 'ditolak' && filled($app->notes) ? $app->notes : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if ($decided->hasPages())
                    <nav class="flex items-center justify-end gap-1 border-t border-rule px-5 py-3 sm:px-6" aria-label="Halaman riwayat">
                        @if ($decided->onFirstPage())
                            <span class="grid h-9 w-9 place-items-center rounded-md text-faint" aria-hidden="true"><x-heroicon-o-chevron-left class="h-4 w-4" /></span>
                        @else
                            <a href="{{ $decided->previousPageUrl() }}" class="grid h-9 w-9 place-items-center rounded-md text-ink hover:bg-sunken" rel="prev" aria-label="Halaman sebelumnya"><x-heroicon-o-chevron-left class="h-4 w-4" /></a>
                        @endif
                        @foreach (range(1, $decided->lastPage()) as $page)
                            @if ($page === $decided->currentPage())
                                <span class="grid h-9 min-w-[2.25rem] place-items-center rounded-md bg-brand px-2 text-sm font-semibold text-white tabular" aria-current="page">{{ $page }}</span>
                            @else
                                <a href="{{ $decided->url($page) }}" class="grid h-9 min-w-[2.25rem] place-items-center rounded-md px-2 text-sm text-ink tabular hover:bg-sunken">{{ $page }}</a>
                            @endif
                        @endforeach
                        @if ($decided->hasMorePages())
                            <a href="{{ $decided->nextPageUrl() }}" class="grid h-9 w-9 place-items-center rounded-md text-ink hover:bg-sunken" rel="next" aria-label="Halaman berikutnya"><x-heroicon-o-chevron-right class="h-4 w-4" /></a>
                        @else
                            <span class="grid h-9 w-9 place-items-center rounded-md text-faint" aria-hidden="true"><x-heroicon-o-chevron-right class="h-4 w-4" /></span>
                        @endif
                    </nav>
                @endif
            @endif
        </div>
    </section>
</x-layouts.app>
