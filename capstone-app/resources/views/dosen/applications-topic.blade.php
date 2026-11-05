{{-- F43 — detail Approval Pengajuan Topik (dosen) untuk satu topik (dibuka "Lihat Pengajuan" di halaman utama): ringkasan kuota
     topik → kartu "Pengajuan Topik" berisi filter peminatan tim, pengajuan aktif (peminatan anggota tim; Detail tim di modal;
     Terima / Tolak dengan alasan opsional) dan, dipisah garis, riwayat keputusan topik ini 3 per halaman.
     Kartu menu utama: tabel selalu pas selebar kartu (teks membungkus), tanpa scroll samping. --}}
@php
    // Judul kolom hijau hanya untuk tabel di bawah judul kartu ("Pengajuan Topik").
    $greenHead = 'bg-brand-tint [&>tr>th]:border-0 [&>tr>th]:py-3.5 [&>tr>th]:text-sm [&>tr>th]:font-semibold [&>tr>th]:text-brand-deep';
    $cells = '[&_td]:break-words [&_td]:px-3 [&_th]:px-3 [&_td:first-child]:pl-5 [&_th:first-child]:pl-5 [&_td:last-child]:pr-5 [&_th:last-child]:pr-5 sm:[&_td:first-child]:pl-6 sm:[&_th:first-child]:pl-6 sm:[&_td:last-child]:pr-6 sm:[&_th:last-child]:pr-6';
    $chip = 'rounded-md border border-rule bg-sunken px-2 py-0.5 text-caption font-medium text-muted';
    // Portofolio selalu jadi tautan: isian tanpa skema diberi https://
    $portfolioHref = fn (string $value) => preg_match('#^https?://#i', $value) ? $value : 'https://'.ltrim($value, '/');
    $remaining = max(0, $topic->quota - $topic->accepted_count);
@endphp
<x-layouts.app :title="'Pengajuan '.$topic->title">
    <x-page-head :title="$topic->title" lede="Menerima tim menjadikan Anda dosen pembimbing tim tersebut secara otomatis. Kuota tidak dapat dilampaui.">
        <x-slot:actions>
            <a href="{{ route('dosen.applications.index') }}" class="btn btn-ghost">Kembali</a>
        </x-slot:actions>
    </x-page-head>

    {{-- Ringkasan topik: peminatan · tim / kuota · ketersediaan --}}
    <section class="panel mb-6 flex flex-wrap items-center gap-x-8 gap-y-3 px-5 py-4 sm:px-6" aria-label="Ringkasan topik">
        <div class="min-w-0">
            <p class="text-caption text-muted">Peminatan</p>
            @if (filled($topic->specializations))
                <ul class="mt-1 flex flex-wrap gap-1.5">@foreach ($topic->specializations as $spec)<li class="{{ $chip }}">{{ $spec }}</li>@endforeach</ul>
            @else
                <p class="mt-1 text-sm text-muted">—</p>
            @endif
        </div>
        <div>
            <p class="text-caption text-muted">Tim / kuota</p>
            <p class="mt-1 tabular font-semibold text-ink">{{ $topic->accepted_count }} / {{ $topic->quota }}</p>
        </div>
        <div>
            <p class="text-caption text-muted">Ketersediaan</p>
            <p class="mt-1 inline-flex items-center gap-2 text-sm font-medium {{ $remaining ? 'text-ok-ink' : 'text-bad-ink' }}">
                <span class="h-2 w-2 shrink-0 rounded-full {{ $remaining ? 'bg-accent' : 'bg-bad' }}" aria-hidden="true"></span>{{ $remaining ? 'Sisa '.$remaining : 'Penuh' }}
            </p>
        </div>
    </section>

    {{-- Pengajuan Topik: pengajuan aktif, lalu riwayat keputusan (satu kartu, dipisah garis) --}}
    <section class="panel overflow-hidden" aria-labelledby="pelamar-judul">
        {{-- Judul kiri · jumlah menunggu di tengah · filter peminatan kanan --}}
        <div class="grid gap-3 px-5 py-4 sm:grid-cols-3 sm:items-center sm:px-6">
            <h2 id="pelamar-judul" class="text-lg font-semibold text-ink">Pengajuan Topik</h2>
            <p class="text-sm text-muted sm:text-center">Menunggu keputusan: <span class="tabular font-medium text-ink">{{ $pending->count() }}</span></p>
            {{-- Filter peminatan anggota tim yang mengajukan --}}
            <form method="GET" action="{{ route('dosen.applications.show', $topic) }}" x-data="autoSubmit" class="sm:justify-self-end">
                <label for="peminatan" class="sr-only">Filter peminatan tim</label>
                <select id="peminatan" name="peminatan" class="input w-full sm:w-64" @change="submit">
                    <option value="">Semua peminatan</option>
                    @foreach ($specializations as $spec)<option value="{{ $spec }}" @selected($peminatan === $spec)>{{ $spec }}</option>@endforeach
                </select>
                <noscript><button class="btn btn-secondary mt-2">Terapkan</button></noscript>
            </form>
        </div>

        @if ($pending->isEmpty())
            <p class="border-t border-rule px-5 py-5 text-sm text-muted sm:px-6">{{ $peminatan !== '' ? 'Tidak ada pengajuan dari tim berpeminatan '.$peminatan.'.' : 'Tidak ada pengajuan topik yang menunggu.' }}</p>
        @else
            <table class="data-table min-w-0 w-full table-fixed border-t border-rule {{ $cells }} [&_tbody_tr:hover_td]:bg-transparent">
                <colgroup><col class="w-[18%]"><col class="w-[20%]"><col class="w-[20%]"><col class="w-[13%]"><col class="w-[14%]"><col class="w-[15%]"></colgroup>
                <thead class="{{ $greenHead }}"><tr><th>Nama tim</th><th>Anggota</th><th>Peminatan</th><th>Portofolio</th><th>Detail tim</th><th class="text-center">Aksi</th></tr></thead>
                <tbody>
                    @foreach ($pending as $app)
                        @php
                            $remaining = max(0, $topic->quota - $topic->accepted_count);
                            $members = $app->team->members->where('invite_status', 'diterima');
                        @endphp
                        <tr x-data="disclosure" class="[&>td]:py-4 [&>td]:align-top">
                            <td class="font-semibold text-ink">{{ $app->team->name }}</td>
                            <td class="text-sm">
                                <ul class="space-y-0.5 text-ink">
                                    @foreach ($members as $m)<li>{{ $m->user->name }}</li>@endforeach
                                </ul>
                            </td>
                            {{-- Peminatan anggota tim (tanpa duplikat) --}}
                            <td>
                                @php $specs = $members->pluck('user.specialization')->filter()->unique()->sort()->values(); @endphp
                                @if ($specs->isNotEmpty())
                                    <ul class="flex flex-wrap gap-1.5">@foreach ($specs as $spec)<li class="{{ $chip }}">{{ $spec }}</li>@endforeach</ul>
                                @else
                                    <span class="text-sm text-muted">—</span>
                                @endif
                            </td>
                            <td class="!align-middle">
                                @if ($app->portfolio_url)
                                    <a href="{{ $portfolioHref($app->portfolio_url) }}" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-sm" title="{{ $app->portfolio_url }}">
                                        <x-heroicon-o-arrow-top-right-on-square class="h-4 w-4" /> Lihat
                                    </a>
                                @else
                                    <span class="text-sm text-muted">—</span>
                                @endif
                            </td>
                            <td class="!align-middle">
                                <div x-data="disclosure">
                                    <button type="button" class="btn btn-secondary btn-sm" @click="toggle">Detail tim</button>
                                    <x-modal width="sm:max-w-3xl">
                                        <x-slot:heading>Detail {{ $app->team->name }}</x-slot:heading>
                                        @include('dosen._application-detail', ['app' => $app])
                                    </x-modal>
                                </div>
                            </td>
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
                <table class="data-table min-w-0 w-full table-fixed {{ $cells }} [&_tbody_tr:hover_td]:bg-transparent">
                    <colgroup><col class="w-[26%]"><col class="w-[18%]"><col class="w-[22%]"><col class="w-[34%]"></colgroup>
                    <thead class="{{ $greenHead }}"><tr><th>Tim</th><th>Keputusan</th><th>Waktu</th><th>Catatan</th></tr></thead>
                    <tbody>
                        @foreach ($decided as $app)
                            <tr class="[&>td]:align-top">
                                <td class="font-medium text-ink">{{ $app->team->name }}</td>
                                <td><x-badge class="!whitespace-normal" :status="$app->status" /></td>
                                <td class="text-sm text-ink">{{ $app->decided_at?->translatedFormat('j M Y, H:i') }}</td>
                                {{-- Catatan hanya ada pada penolakan (alasan opsional); menerima tim tidak disertai catatan --}}
                                <td class="text-sm text-muted">{{ $app->status === 'ditolak' && filled($app->notes) ? $app->notes : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <x-history-pager :paginator="$decided" />
            @endif
        </div>
    </section>
</x-layouts.app>
