{{-- F43 — Approval Pengajuan Topik (dosen), halaman utama: daftar topik milik dosen (peminatan, tim / kuota, ketersediaan,
     status) dengan tombol "Lihat Pengajuan" per topik → halaman detail berisi pengajuan tim & riwayat keputusan topik itu
     (dosen/applications-topic). Kartu menu utama: tabel selalu pas selebar kartu (teks membungkus), tanpa scroll samping. --}}
@php
    $cells = '[&_td]:break-words [&_td]:px-3 [&_th]:px-3 [&_td:first-child]:pl-5 [&_th:first-child]:pl-5 [&_td:last-child]:pr-5 [&_th:last-child]:pr-5 sm:[&_td:first-child]:pl-6 sm:[&_th:first-child]:pl-6 sm:[&_td:last-child]:pr-6 sm:[&_th:last-child]:pr-6';
    $chip = 'rounded-md border border-rule bg-sunken px-2 py-0.5 text-caption font-medium text-muted';
    $topicStatus = ['diterima' => ['diterima', 'Dipublikasikan'], 'menunggu' => ['menunggu', 'Menunggu admin'], 'ditolak' => ['ditolak', 'Ditolak admin']];
@endphp
<x-layouts.app title="Approval Pengajuan Topik">
    <x-page-head title="Approval Pengajuan Topik" lede="Menerima tim menjadikan Anda dosen pembimbing tim tersebut secara otomatis. Kuota tidak dapat dilampaui." />

    {{-- Topik milik dosen: pilih topik untuk melihat tim yang mengajukan --}}
    <section class="panel mb-6 overflow-hidden" aria-label="Kuota topik saya">
        @if ($topics->isEmpty())
            <x-empty title="Anda belum memiliki topik" icon="light-bulb" />
        @else
            <table class="data-table min-w-0 w-full table-fixed {{ $cells }} [&_tbody_tr:hover_td]:bg-transparent">
                <colgroup><col class="w-[27%]"><col class="w-[21%]"><col class="w-[10%]"><col class="w-[11%]"><col class="w-[14%]"><col class="w-[17%]"></colgroup>
                <thead><tr><th>Topik Saya</th><th>Peminatan</th><th class="text-center">Tim / kuota</th><th>Ketersediaan</th><th>Status</th><th><span class="sr-only">Pengajuan</span></th></tr></thead>
                <tbody>
                    @foreach ($topics as $topic)
                        @php
                            $remaining = max(0, $topic->quota - $topic->accepted_count);
                            [$st, $stLabel] = $topicStatus[$topic->status] ?? [$topic->status, ucfirst($topic->status)];
                        @endphp
                        <tr class="[&>td]:py-4">
                            <td class="font-semibold leading-snug text-ink">{{ $topic->title }}</td>
                            <td>
                                @if (filled($topic->specializations))
                                    <ul class="flex flex-wrap gap-1.5">@foreach ($topic->specializations as $spec)<li class="{{ $chip }}">{{ $spec }}</li>@endforeach</ul>
                                @else
                                    <span class="text-sm text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-center tabular text-ink">{{ $topic->accepted_count }} / {{ $topic->quota }}</td>
                            <td>
                                <span class="inline-flex items-center gap-2 text-sm font-medium {{ $remaining ? 'text-ok-ink' : 'text-bad-ink' }}">
                                    <span class="h-2 w-2 shrink-0 rounded-full {{ $remaining ? 'bg-accent' : 'bg-bad' }}" aria-hidden="true"></span>{{ $remaining ? 'Sisa '.$remaining : 'Penuh' }}
                                </span>
                            </td>
                            <td><x-badge class="!whitespace-normal" :status="$st" :label="$stLabel" /></td>
                            <td class="text-right">
                                <a href="{{ route('dosen.applications.show', $topic) }}" class="btn btn-secondary btn-sm">
                                    Lihat Pengajuan
                                    @if ($topic->pending_count)<span class="rounded-full bg-bad-ink px-2 text-caption font-semibold leading-5 text-white tabular" title="{{ $topic->pending_count }} pengajuan menunggu keputusan">{{ $topic->pending_count }}</span>@endif
                                    <span class="sr-only"> {{ $topic->title }}</span>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

</x-layouts.app>
