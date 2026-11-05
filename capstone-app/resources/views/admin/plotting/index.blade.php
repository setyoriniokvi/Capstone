{{-- F10 — Plotting pembimbing & penguji: kartu tab (Plotting Tim Mahasiswa / Plotting Dosen) lalu isi tab:
     admin/plotting/_teams (daftar tim, plot dosen per peran sesuai peminatan tim) atau admin/plotting/_lecturers
     (ringkasan flag, load balancing dosen, plot tim per dosen). --}}
@php
    // Judul kolom hijau & sel selebar kartu (sama dengan Approval Registrasi Tim)
    $greenHead = 'bg-brand-tint [&>tr>th]:border-0 [&>tr>th]:py-3.5 [&>tr>th]:text-sm [&>tr>th]:font-semibold [&>tr>th]:text-brand-deep';
    $cells = '[&_td]:break-words [&_td]:px-3 [&_th]:px-3 [&_td:first-child]:pl-5 [&_th:first-child]:pl-5 [&_td:last-child]:pr-5 [&_th:last-child]:pr-5 sm:[&_td:first-child]:pl-6 sm:[&_th:first-child]:pl-6 sm:[&_td:last-child]:pr-6 sm:[&_th:last-child]:pr-6';
@endphp
<x-layouts.app title="Plotting Dosen">
    <x-page-head title="Plotting pembimbing & penguji" lede="Plot dosen per tim sesuai peminatan tim, atau pilih dosen lalu plot tim bimbingan dan tim ujinya. Sisa kuota (dari maksimum) berkurang setiap ada tim yang diplot. Flag OVER/UNDER bersifat informatif dan tidak memblokir plotting." />

    {{-- Tab --}}
    <section class="panel mb-6 p-2" aria-label="Jenis plotting">
        <nav class="grid gap-1 sm:grid-cols-2">
            @foreach (['tim' => 'Plotting Tim Mahasiswa', 'dosen' => 'Plotting Dosen'] as $key => $label)
                <a href="{{ route('admin.plotting.index', $key === 'dosen' ? ['tab' => 'dosen'] : []) }}" @if ($tab === $key) aria-current="page" @endif
                   class="flex min-h-[44px] items-center justify-center gap-2 rounded-lg px-4 text-sm font-medium {{ $tab === $key ? 'bg-brand-tint text-brand-deep' : 'text-muted hover:bg-sunken hover:text-ink' }}">
                    {{ $label }}
                </a>
            @endforeach
        </nav>
    </section>

    @if (! $period)
        <div class="panel"><x-empty title="Belum ada periode aktif" icon="calendar-days" /></div>
    @else
        @include($tab === 'dosen' ? 'admin.plotting._lecturers' : 'admin.plotting._teams')
    @endif
</x-layouts.app>
