{{-- Bar bertumpuk + legenda (dashboard admin: Registrasi Tim, Status Mahasiswa, Status Dosen).
     Arahkan kursor / fokus ke potongan bar untuk melihat status & jumlahnya (warna sama dengan potongan).
     title  : judul (tautan ke href)
     total  : teks di kanan judul, mis. "37 tim"
     items  : [key => [label, kelas warna latar]]
     counts : [key => jumlah] --}}
@props(['title', 'total', 'href', 'items', 'counts'])
@php
    $counts = collect($counts);
    $sum = max(1, $counts->sum());
    $visible = collect($items)->keys()->filter(fn ($k) => ($counts[$k] ?? 0) > 0)->values();
@endphp
<div {{ $attributes->merge(['class' => 'px-4 py-5 sm:px-6']) }}>
    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
        <h2 class="text-lg font-semibold text-ink"><a href="{{ $href }}" class="hover:underline">{{ $title }}</a></h2>
        <span class="tabular text-sm text-muted">{{ $total }}</span>
    </div>
    {{-- Tanpa overflow-hidden agar tooltip tidak terpotong; ujung bar dibulatkan per potongan. --}}
    <div class="mt-4 flex h-2.5 gap-px rounded-full bg-sunken" role="img"
         aria-label="{{ $title }}: {{ collect($items)->map(fn ($i, $k) => $i[0].' '.($counts[$k] ?? 0))->implode(', ') }}">
        @foreach ($visible as $i => $key)
            @php [$label, $color] = $items[$key]; $pct = $counts[$key] / $sum * 100; @endphp
            <span class="group relative {{ $color }} {{ $i === 0 ? 'rounded-l-full' : '' }} {{ $i === $visible->count() - 1 ? 'rounded-r-full' : '' }} outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2"
                  style="width: {{ $pct }}%" tabindex="0">
                <span role="tooltip"
                      class="pointer-events-none invisible absolute bottom-full left-1/2 z-20 mb-2 flex -translate-x-1/2 items-center gap-2 whitespace-nowrap rounded-md border border-rule bg-surface px-2.5 py-1.5 text-sm opacity-0 shadow-panel transition-opacity duration-150 group-hover:visible group-hover:opacity-100 group-focus-visible:visible group-focus-visible:opacity-100">
                    <span class="h-2.5 w-2.5 rounded-full {{ $color }}" aria-hidden="true"></span>
                    <span class="text-muted">{{ $label }}</span>
                    <span class="tabular font-semibold text-ink">{{ $counts[$key] }}</span>
                    <span class="tabular text-muted">({{ round($pct) }}%)</span>
                </span>
            </span>
        @endforeach
    </div>
    <ul class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-sm">
        @foreach ($items as $key => [$label, $color])
            <li class="flex items-center gap-2 text-muted">
                <span class="h-2.5 w-2.5 rounded-full {{ $color }}" aria-hidden="true"></span>{{ $label }}
                <span class="tabular font-semibold text-ink">{{ $counts[$key] ?? 0 }}</span>
            </li>
        @endforeach
    </ul>
</div>
