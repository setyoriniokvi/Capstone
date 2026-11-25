{{-- F43 — Approval Pengajuan Topik (dosen), halaman utama: daftar topik milik dosen yang sudah terbit (disetujui admin) —
     Topik Saya | Peminatan | Tim / kuota (+ Sisa n / Penuh di bawahnya) | tombol "Lihat Pengajuan" → halaman detail berisi
     pengajuan tim & riwayat keputusan topik itu (dosen/applications-topic). Kartu menu utama: tabel pas selebar kartu. --}}
@php
    // Jarak antarkolom lebih rapat di layar < lg agar tabel tetap muat selebar kartu
    $cells = '[&_td]:break-words [&_td]:px-2 [&_th]:px-2 lg:[&_td]:px-3 lg:[&_th]:px-3 [&_td:first-child]:pl-5 [&_th:first-child]:pl-5 [&_td:last-child]:pr-5 [&_th:last-child]:pr-5 sm:[&_td:first-child]:pl-6 sm:[&_th:first-child]:pl-6 sm:[&_td:last-child]:pr-6 sm:[&_th:last-child]:pr-6';
    $chip = 'rounded-md border border-rule bg-sunken px-2 py-0.5 text-caption font-medium text-muted';
@endphp
<x-layouts.app title="Approval Pengajuan Topik">
    <x-page-head title="Approval Pengajuan Topik" lede="Menerima tim menjadikan Anda dosen pembimbing tim tersebut secara otomatis." />

    {{-- Topik milik dosen: pilih topik untuk melihat tim yang mengajukan --}}
    <section data-sort-scope class="panel mb-6 overflow-hidden" aria-label="Kuota topik saya">
        @if ($topics->isEmpty())
            <x-empty title="Belum ada topik Anda yang terbit" icon="light-bulb">Topik muncul di sini setelah disetujui admin di Approval Topik.</x-empty>
        @else
            <div class="flex justify-end border-b border-rule px-5 py-3 sm:px-6"><x-sort-by id="urut-topik-saya" default="topik" :options="['topik' => 'Topik Saya', 'kategori' => 'Kategori (Ketersediaan)']" /></div>
            <table class="data-table min-w-0 w-full {{ $cells }} [&_tbody_tr:hover_td]:bg-transparent">
                <thead><tr><th>Topik Saya</th><th>Peminatan</th><th class="whitespace-nowrap !text-center">Tim / kuota</th><th><span class="sr-only">Pengajuan</span></th></tr></thead>
                <tbody data-sort-list>
                    @foreach ($topics as $topic)
                        @php $remaining = max(0, $topic->quota - $topic->accepted_count); @endphp
                        <tr data-sort-item data-sort-topik="{{ $topic->title }}" data-sort-kategori="{{ $remaining ? 'Sisa' : 'Penuh' }}" class="[&>td]:py-4">
                            <td class="font-semibold leading-snug text-ink">{{ $topic->title }}</td>
                            <td>
                                @if (filled($topic->specializations))
                                    <ul class="flex flex-wrap gap-1.5">@foreach ($topic->specializations as $spec)<li class="{{ $chip }}">{{ $spec }}</li>@endforeach</ul>
                                @else
                                    <span class="text-sm text-muted">-</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap text-center">
                                <p class="tabular text-ink">{{ $topic->accepted_count }} / {{ $topic->quota }}</p>
                                <p class="mt-1 inline-flex items-center gap-1.5 text-sm font-medium {{ $remaining ? 'text-ok-ink' : 'text-bad-ink' }}">
                                    <span class="h-2 w-2 shrink-0 rounded-full {{ $remaining ? 'bg-accent' : 'bg-bad' }}" aria-hidden="true"></span>{{ $remaining ? 'Sisa '.$remaining : 'Penuh' }}
                                </p>
                            </td>
                            <td class="w-px whitespace-nowrap text-right">
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
