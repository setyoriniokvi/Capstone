{{-- Aturan semua tabel: bungkus dengan komponen ini. Bila tabel lebih lebar dari layar (sampai mobile), muncul scrollbar
     horizontal di bawah tabel dan tombol bulat < (kiri) / > (kanan) di tengah tinggi tabel yang menggeser sekitar satu
     layar (Alpine "scrollPager"). Tiap tombol hilang bila sisi itu sudah mentok. Beri tabel lebar minimum (min-w-*)
     agar isinya tidak terhimpit di layar sempit. --}}
@php
    $btn = 'absolute top-1/2 z-10 grid h-11 w-11 -translate-y-1/2 place-items-center rounded-full border border-rule bg-surface text-ink shadow-panel hover:bg-sunken focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand';
@endphp
<div x-data="scrollPager" {{ $attributes->merge(['class' => 'relative']) }}>
    <button type="button" x-show="canLeft" x-cloak x-transition.opacity @click="left" aria-label="Geser tabel ke kiri" class="{{ $btn }} left-3">
        <x-heroicon-o-chevron-left class="h-5 w-5" />
    </button>
    <button type="button" x-show="canRight" x-cloak x-transition.opacity @click="right" aria-label="Geser tabel ke kanan" class="{{ $btn }} right-3">
        <x-heroicon-o-chevron-right class="h-5 w-5" />
    </button>
    <div x-ref="scroller" @scroll.passive="update" class="scroll-x-visible">
        {{ $slot }}
    </div>
</div>
