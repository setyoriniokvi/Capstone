{{-- Tombol ringkas "⇅ Sort ▾" yang membuka menu urutan: daftar kolom (titik = terpilih), garis, lalu Ascending / Descending
     (teks A→Z, angka kecil→besar, tanggal terlama→terbaru; Descending kebalikannya).
     Letakkan di sebelah kanan kolom cari (bila kartu punya kolom cari); tanpa kolom cari, di kanan judul kartu.
     Dua jenis bawaan (dipilih di tiap pemakaian lewat default & dir):
       data tetap / sudah disetujui  → kolom paling kiri, Ascending; pilihan: kolom kiri, nama, tanggal, kategori
       data menunggu & log           → tanggal, Descending (paling baru di atas); pilihan: tanggal, nama, kategori
     mode client : urut langsung di browser (Alpine "sortBy"). Cakupan = elemen terdekat [data-sort-scope]; di dalamnya
                   setiap [data-sort-list] diurutkan: anaknya yang ber-[data-sort-item] membawa data-sort-<kunci> (angka
                   dibandingkan sebagai angka, teks secara alfabet; kosong selalu di akhir). Baris ber-[data-sort-follow]
                   tepat setelahnya ikut pindah; baris lain (mis. total) tetap di bawah. Sel [data-sort-no] dinomori ulang.
                   Setelah urut, event "sorted" dikirim ke cakupan (mis. bulkApproval menyusun ulang halaman).
     mode server : pilihan berupa tautan ?urut=&arah= (lihat App\Support\Sorting); parameter lain ikut, halaman kembali ke 1.
                   Karena berupa tautan (bukan form), boleh diletakkan di dalam form cari / filter.
     Menu memakai posisi fixed sehingga tidak terpotong kartu ber-overflow-hidden.
     options : [kunci => label]   default : kunci bawaan   dir : asc|desc   id : id unik   tall : tinggi 48px (kolom cari besar)
     target  : (mode client) selektor kartu yang diurutkan bila tombol berada di luar kartu itu (mis. kolom cari di atas kartu) --}}
@props(['options', 'default', 'dir' => 'asc', 'mode' => 'client', 'id' => 'urutkan', 'name' => 'urut', 'dirName' => 'arah', 'pageName' => 'page', 'tall' => false, 'target' => null, 'fragment' => null])
@php
    $server = $mode === 'server';
    $current = $server && array_key_exists((string) request($name), $options) ? request($name) : $default;
    $currentDir = $server && in_array(request($dirName), ['asc', 'desc'], true) ? request($dirName) : $dir;
    $directions = ['asc' => 'Ascending', 'desc' => 'Descending'];
    $item = 'flex w-full items-center gap-2.5 px-3 py-2 text-left text-sm text-ink hover:bg-sunken focus-visible:bg-sunken focus-visible:outline-none';
    $dot = 'h-1.5 w-1.5 rounded-full bg-ink';
    $url = fn (string $key, string $direction) => request()->fullUrlWithQuery([$name => $key, $dirName => $direction, $pageName => null]).($fragment ? "#{$fragment}" : '');
@endphp
<div x-data="{{ $server ? 'sortMenu' : 'sortBy' }}" @if (! $server) data-default="{{ $default }}" data-dir="{{ $dir }}" @if ($target) data-sort-target="{{ $target }}" @endif @endif
     @click.outside="closeMenu" @keydown.escape.window="closeMenu" @scroll.window="closeMenu" @resize.window="closeMenu"
     {{ $attributes->merge(['class' => 'relative shrink-0']) }}>
    <button type="button" id="{{ $id }}" x-ref="button" @click="toggleMenu" aria-haspopup="menu" :aria-expanded="open ? 'true' : 'false'"
            class="btn btn-secondary {{ $tall ? 'min-h-[48px] rounded-xl' : '' }} gap-1.5 px-3" title="Urutkan">
        <x-heroicon-o-arrows-up-down class="h-4 w-4 text-brand" />Sort<x-heroicon-m-chevron-down class="h-4 w-4 text-muted" />
    </button>
    <div x-ref="menu" x-show="open" x-cloak role="menu" aria-labelledby="{{ $id }}"
         class="fixed z-50 w-56 overflow-hidden rounded-lg border border-rule bg-surface py-1 shadow-lg">
        @foreach ($options as $key => $label)
            @if ($server)
                <a href="{{ $url($key, $currentDir) }}" role="menuitemradio" aria-checked="{{ $key === $current ? 'true' : 'false' }}" class="{{ $item }}">
                    <span class="grid w-3 place-items-center">@if ($key === $current)<span class="{{ $dot }}"></span>@endif</span>{{ $label }}
                </a>
            @else
                <button type="button" role="menuitemradio" data-key="{{ $key }}" @click="pickKey" :aria-checked="key === '{{ $key }}' ? 'true' : 'false'" class="{{ $item }}">
                    <span class="grid w-3 place-items-center"><span x-show="key === '{{ $key }}'" class="{{ $dot }}"></span></span>{{ $label }}
                </button>
            @endif
        @endforeach
        <div class="my-1 border-t border-rule" role="separator"></div>
        @foreach ($directions as $value => $label)
            @if ($server)
                <a href="{{ $url($current, $value) }}" role="menuitemradio" aria-checked="{{ $value === $currentDir ? 'true' : 'false' }}" class="{{ $item }}">
                    <span class="grid w-3 place-items-center">@if ($value === $currentDir)<span class="{{ $dot }}"></span>@endif</span>{{ $label }}
                </a>
            @else
                <button type="button" role="menuitemradio" data-dir="{{ $value }}" @click="pickDir" :aria-checked="dir === '{{ $value }}' ? 'true' : 'false'" class="{{ $item }}">
                    <span class="grid w-3 place-items-center"><span x-show="dir === '{{ $value }}'" class="{{ $dot }}"></span></span>{{ $label }}
                </button>
            @endif
        @endforeach
    </div>
</div>
