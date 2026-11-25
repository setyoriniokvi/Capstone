{{-- Master Data: tab Master Data Mahasiswa / Master Data Dosen. Tiap proses punya kartu log sendiri (Cari | Sort | filter,
     "Tampilkan [n] data" + ‹ ›; parameter berawalan kunci kartu agar tidak saling bentrok):
       Mahasiswa: Log Registrasi Tim (admin/teams/_history) · Pengajuan Topik · Bimbingan · Submission · Peer Review (_student-cards)
       Dosen    : Status Dosen · Pengajuan Topik Dosen · Penilaian (_lecturer-cards)
     (Log perubahan data tetap dicatat MasterDataLogger, tetapi tidak ditampilkan di halaman ini.) --}}
@php
    $greenHead = 'bg-brand-tint [&>tr>th]:border-0 [&>tr>th]:py-3.5 [&>tr>th]:text-sm [&>tr>th]:font-semibold [&>tr>th]:text-brand-deep';
@endphp
<x-layouts.app title="Master Data">
    <x-page-head title="Master Data" />

    {{-- Tab --}}
    <section class="panel mb-6 p-2" aria-label="Jenis master data">
        <nav class="grid gap-1 sm:grid-cols-2">
            @foreach (['mahasiswa' => 'Master Data Mahasiswa', 'dosen' => 'Master Data Dosen'] as $key => $label)
                <a href="{{ route('admin.master-data.index', $key === 'dosen' ? ['tab' => 'dosen'] : []) }}" @if ($tab === $key) aria-current="page" @endif
                   class="flex min-h-[44px] items-center justify-center gap-2 rounded-lg px-4 text-sm font-medium {{ $tab === $key ? 'bg-brand-tint text-brand-deep' : 'text-muted hover:bg-sunken hover:text-ink' }}">
                    {{ $label }}
                </a>
            @endforeach
        </nav>
    </section>

    @isset($decided)
        @include('admin.teams._history')
        @include('admin.master-data._student-cards')
    @endisset
    @isset($assignmentLog)
        @include('admin.master-data._lecturer-cards')
    @endisset
</x-layouts.app>
