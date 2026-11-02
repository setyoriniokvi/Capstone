{{-- Navigasi halaman riwayat di kanan bawah kartu: ‹ 1 2 3 › (Approval Pengajuan Topik dosen, Approval Registrasi Tim admin).
     paginator : LengthAwarePaginator; tidak tampil bila hanya satu halaman. --}}
@props(['paginator', 'label' => 'Halaman riwayat'])
@if ($paginator->hasPages())
    <nav {{ $attributes->merge(['class' => 'flex items-center justify-end gap-1 border-t border-rule px-5 py-3 sm:px-6']) }} aria-label="{{ $label }}">
        @if ($paginator->onFirstPage())
            <span class="grid h-9 w-9 place-items-center rounded-md text-faint" aria-hidden="true"><x-heroicon-o-chevron-left class="h-4 w-4" /></span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="grid h-9 w-9 place-items-center rounded-md text-ink hover:bg-sunken" rel="prev" aria-label="Halaman sebelumnya"><x-heroicon-o-chevron-left class="h-4 w-4" /></a>
        @endif
        @foreach (range(1, $paginator->lastPage()) as $page)
            @if ($page === $paginator->currentPage())
                <span class="grid h-9 min-w-[2.25rem] place-items-center rounded-md bg-brand px-2 text-sm font-semibold text-white tabular" aria-current="page">{{ $page }}</span>
            @else
                <a href="{{ $paginator->url($page) }}" class="grid h-9 min-w-[2.25rem] place-items-center rounded-md px-2 text-sm text-ink tabular hover:bg-sunken">{{ $page }}</a>
            @endif
        @endforeach
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="grid h-9 w-9 place-items-center rounded-md text-ink hover:bg-sunken" rel="next" aria-label="Halaman berikutnya"><x-heroicon-o-chevron-right class="h-4 w-4" /></a>
        @else
            <span class="grid h-9 w-9 place-items-center rounded-md text-faint" aria-hidden="true"><x-heroicon-o-chevron-right class="h-4 w-4" /></span>
        @endif
    </nav>
@endif
