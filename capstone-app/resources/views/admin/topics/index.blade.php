{{-- F7 — Approval Topik admin (pola Approval Registrasi Tim): kartu tab (Approval Topik Mandiri / Approval Topik Dosen)
     paling atas, lalu isi tab: instruksi (admin/topics/_instruction), kata kunci terlarang jenis topik tersebut (admin/topics/_keywords), kemudian
     admin/topics/_mandiri (menunggu approval + riwayat) atau admin/topics/_dosen (menunggu approval + bank topik dosen
     yang dapat ditambah/diubah/dihapus admin). --}}
@php
    // Judul kolom hijau & sel selebar kartu (sama dengan Approval Registrasi Tim)
    $greenHead = 'bg-brand-tint [&>tr>th]:border-0 [&>tr>th]:py-3.5 [&>tr>th]:text-sm [&>tr>th]:font-semibold [&>tr>th]:text-brand-deep';
    $cells = '[&_td]:break-words [&_td]:px-3 [&_th]:px-3 [&_td:first-child]:pl-5 [&_th:first-child]:pl-5 [&_td:last-child]:pr-5 [&_th:last-child]:pr-5 sm:[&_td:first-child]:pl-6 sm:[&_th:first-child]:pl-6 sm:[&_td:last-child]:pr-6 sm:[&_th:last-child]:pr-6';
@endphp
<x-layouts.app title="Approval Topik">
    <x-page-head title="Approval Topik" lede="Topik mandiri bersifat opsional dan dapat diajukan tim bersamaan dengan lamaran topik dosen. Topik dosen terbit di bank topik setelah disetujui. Sistem menandai kata kunci yang tidak diperbolehkan." />

    @error('topic')<p class="mb-6 rounded-lg border border-bad-line bg-bad-tint px-4 py-3 text-sm text-bad-ink">{{ $message }}</p>@enderror
    @error('notes')<p class="mb-6 rounded-lg border border-bad-line bg-bad-tint px-4 py-3 text-sm text-bad-ink">{{ $message }}</p>@enderror
    @error('topics')<p class="mb-6 rounded-lg border border-bad-line bg-bad-tint px-4 py-3 text-sm text-bad-ink">{{ $message }}</p>@enderror

    {{-- Tab --}}
    <section class="panel mb-6 p-2" aria-label="Jenis topik">
        <nav class="grid gap-1 sm:grid-cols-2">
            @foreach (['mandiri' => 'Approval Topik Mandiri', 'dosen' => 'Approval Topik Dosen'] as $key => $label)
                <a href="{{ route('admin.topics.index', ['tab' => $key]) }}" @if ($tab === $key) aria-current="page" @endif
                   class="flex min-h-[44px] items-center justify-center gap-2 rounded-lg px-4 text-sm font-medium {{ $tab === $key ? 'bg-brand-tint text-brand-deep' : 'text-muted hover:bg-sunken hover:text-ink' }}">
                    {{ $label }}
                    @if ($pendingCount[$key] ?? 0)<span class="rounded-full bg-bad-ink px-2 text-caption font-semibold leading-5 text-white tabular">{{ $pendingCount[$key] }}</span>@endif
                </a>
            @endforeach
        </nav>
    </section>

    {{-- Instruksi (opsional) & kata kunci terlarang khusus jenis topik pada tab ini --}}
    @include('admin.topics._instruction')
    @include('admin.topics._keywords')

    @include($tab === 'dosen' ? 'admin.topics._dosen' : 'admin.topics._mandiri')
</x-layouts.app>
