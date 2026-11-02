{{-- Pembungkus tabel lebar yang digeser ke samping: scrollbar horizontal selalu tampil, dan tombol bulat
     < / > di tengah tinggi tabel untuk langsung ke ujung kiri / kanan (Alpine "scrollPager").
     Tombol hanya muncul bila masih ada sisi yang bisa dituju. --}}
@php
    $btn = 'absolute top-1/2 z-10 grid h-11 w-11 -translate-y-1/2 place-items-center rounded-full border border-rule bg-surface text-ink shadow-panel hover:bg-sunken focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand';
@endphp
<div x-data="scrollPager" {{ $attributes->merge(['class' => 'relative']) }}>
    <button type="button" x-show="canLeft" x-cloak x-transition.opacity @click="toStart" aria-label="Geser tabel ke paling kiri" class="{{ $btn }} left-3">
        <x-heroicon-o-chevron-left class="h-5 w-5" />
    </button>
    <button type="button" x-show="canRight" x-cloak x-transition.opacity @click="toEnd" aria-label="Geser tabel ke paling kanan" class="{{ $btn }} right-3">
        <x-heroicon-o-chevron-right class="h-5 w-5" />
    </button>
    <div x-ref="scroller" @scroll.passive="update" class="scroll-x-visible">
        {{ $slot }}
    </div>
</div>
