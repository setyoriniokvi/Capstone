{{-- Kartu ringkasan dashboard. Ikon kecil sejajar label (bukan ubin ikon di atas), lalu isi bebas.
     Isi yang diberi kelas mt-auto menempel ke dasar kartu, sehingga kartu sebaris tetap rata.
     href : bila diisi, tombol panah ">" di pojok kanan atas membuka halaman rinciannya (linkLabel untuk pembaca layar). --}}
@props(['icon', 'label', 'href' => null, 'linkLabel' => null])
<section {{ $attributes->merge(['class' => 'panel flex min-w-0 flex-col p-5']) }}>
    <div class="flex items-center justify-between gap-2">
        <h2 class="flex min-w-0 items-center gap-2 text-sm text-muted">
            <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-4 w-4 shrink-0" aria-hidden="true" />
            {{ $label }}
        </h2>
        @if ($href)
            <a href="{{ $href }}" class="-my-3 -mr-3 grid h-11 w-11 shrink-0 place-items-center rounded-full text-faint hover:bg-sunken hover:text-ink" title="{{ $linkLabel ?? $label }}">
                <x-heroicon-o-chevron-right class="h-5 w-5" aria-hidden="true" />
                <span class="sr-only">{{ $linkLabel ?? $label }}</span>
            </a>
        @endif
    </div>
    {{ $slot }}
</section>
