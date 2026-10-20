{{-- Kartu ringkasan angka (tidak dapat diklik): satu baris kolom sejajar di layar ≥ sm, 2 kolom di layar sempit.
     stats: list of ['value' => int, 'label' => string, 'href' => ?string, 'linkLabel' => ?string]
     href (opsional): tombol panah ">" di pojok kanan atas kotak untuk membuka halaman rinciannya. --}}
@props(['stats'])
@php $cols = count($stats); @endphp
<section {{ $attributes->merge(['class' => 'panel grid grid-cols-2 sm:grid-cols-[repeat(var(--stat-cols),minmax(0,1fr))] divide-rule max-sm:[&>*:nth-child(-n+2)]:border-b max-sm:[&>*:nth-child(even)]:border-l sm:divide-x [&>*]:border-rule']) }}
         style="--stat-cols: {{ $cols }}" aria-label="Ringkasan">
    @foreach ($stats as $stat)
        <div class="min-w-0 px-5 py-5 sm:px-8 sm:py-6">
            <div class="flex items-start justify-between gap-2">
                <p class="tabular text-[2rem] font-bold leading-none tracking-tight text-brand">{{ $stat['value'] }}</p>
                @if ($stat['href'] ?? null)
                    <a href="{{ $stat['href'] }}" class="-mr-3 -mt-3 grid h-11 w-11 shrink-0 place-items-center rounded-full text-faint hover:bg-sunken hover:text-ink" title="{{ $stat['linkLabel'] ?? $stat['label'] }}">
                        <x-heroicon-o-chevron-right class="h-5 w-5" aria-hidden="true" />
                        <span class="sr-only">{{ $stat['linkLabel'] ?? $stat['label'] }}</span>
                    </a>
                @endif
            </div>
            <p class="mt-2 text-sm font-medium text-ink">{{ $stat['label'] }}</p>
        </div>
    @endforeach
</section>
