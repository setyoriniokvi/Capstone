{{-- Footer kartu untuk daftar berhalaman di server: kiri "Tampilkan [10] data" (mengganti ?per_page, kembali ke halaman 1,
     filter lain tetap), kanan ‹ 1 2 3 ›. Tampil walau hanya satu halaman agar jumlah data tetap bisa diatur.
     paginator : LengthAwarePaginator   id : id unik dropdown   label : nama navigasi halaman (aksesibilitas)
     perPageName : nama parameter jumlah data (bawaan per_page; beberapa kartu dalam satu halaman memakai nama sendiri)
     fragment : id kartu, agar setelah pindah halaman / ganti jumlah data browser kembali ke kartu itu --}}
@props(['paginator', 'id' => 'per-page', 'label' => 'Halaman data', 'options' => \App\Support\PerPage::OPTIONS, 'perPageName' => 'per_page', 'fragment' => null])
@php
    $last = $paginator->lastPage();
    $current = $paginator->currentPage();
    // Nomor halaman ringkas: 1 … (sekitar halaman aktif) … terakhir
    $pages = collect([1, $last, $current - 1, $current, $current + 1])->filter(fn ($n) => $n >= 1 && $n <= $last)->unique()->sort()->values();
@endphp
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center justify-between gap-3 border-t border-rule px-5 py-3 sm:px-6']) }}>
    <form method="GET" @if ($fragment) action="{{ url()->current() }}#{{ $fragment }}" @endif x-data="autoSubmit" @change="submit" class="flex items-center gap-2 text-sm text-muted">
        @foreach (request()->except([$paginator->getPageName(), $perPageName]) as $name => $value)
            @if (is_scalar($value))<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endif
        @endforeach
        <label for="{{ $id }}">Tampilkan</label>
        <select id="{{ $id }}" name="{{ $perPageName }}" class="input min-h-[36px] w-auto py-1">
            @foreach ($options as $n)<option value="{{ $n }}" @selected($n === $paginator->perPage())>{{ $n }}</option>@endforeach
        </select>
        <span>data</span>
        <noscript><button type="submit" class="btn btn-secondary btn-sm">Terapkan</button></noscript>
    </form>
    <nav class="flex items-center gap-1" aria-label="{{ $label }}">
        @if ($paginator->onFirstPage())
            <span class="grid h-9 w-9 place-items-center rounded-md text-faint" aria-hidden="true"><x-heroicon-o-chevron-left class="h-4 w-4" /></span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="grid h-9 w-9 place-items-center rounded-md text-ink hover:bg-sunken" rel="prev" aria-label="Halaman sebelumnya"><x-heroicon-o-chevron-left class="h-4 w-4" /></a>
        @endif
        @foreach ($pages as $i => $page)
            @if ($i > 0 && $page - $pages[$i - 1] > 1)<span class="px-1 text-sm text-faint" aria-hidden="true">…</span>@endif
            @if ($page === $current)
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
</div>
