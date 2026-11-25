{{-- Kartu log proses (Master Data): judul + jumlah data, lalu Cari | Sort | filter dropdown, tabel (slot), dan di bawah
     "Tampilkan [5] data" + ‹ 1 2 3 ›. Disaring di server lewat App\Support\ProcessTable; parameter memakai awalan $key
     sehingga beberapa kartu dalam satu halaman tidak saling bentrok, dan setelah menyaring browser kembali ke kartu ini (#card).
     card      : awalan parameter & id kartu        paginator : hasil ProcessTable::paginate (atau paginator Eloquent)
     sorts     : [kunci => label] pilihan Sort       default / dir : urutan bawaan
     filters   : [nama => ['label' => ..., 'options' => [nilai => label]]] — dropdown di samping kolom cari
     placeholder : petunjuk kolom cari               empty : teks bila belum ada data sama sekali
     sortName / dirName / perPageName : nama parameter bila berbeda dari {key}_urut / {key}_arah / {key}_per --}}
@props(['card', 'title', 'paginator', 'sorts', 'default', 'dir' => 'desc', 'filters' => [], 'placeholder' => 'Cari', 'empty' => 'Belum ada data.',
    'sortName' => null, 'dirName' => null, 'perPageName' => null, 'sortId' => null, 'unit' => 'data'])
@php
    $key = $card;
    $sortName ??= "{$key}_urut";
    $dirName ??= "{$key}_arah";
    $perPageName ??= "{$key}_per";
    $qName = "{$key}_q";
    $own = array_merge([$qName, $paginator->getPageName()], array_map(fn ($f) => "{$key}_{$f}", array_keys($filters)));
    $filtered = request()->filled($qName) || collect(array_keys($filters))->contains(fn ($f) => request()->filled("{$key}_{$f}"));
@endphp
<section id="{{ $key }}" {{ $attributes->merge(['class' => 'panel mb-6 scroll-mt-6 overflow-hidden']) }} aria-labelledby="{{ $key }}-judul">
    <div class="flex flex-wrap items-center justify-between gap-3 px-5 pt-5 sm:px-6">
        <h2 id="{{ $key }}-judul" class="text-lg font-semibold text-ink">{{ $title }}</h2>
        <span class="text-sm text-muted">Total: <span class="tabular font-medium text-ink">{{ $paginator->total() }}</span> {{ $unit }}</span>
    </div>
    <form method="GET" action="{{ url()->current() }}#{{ $key }}" x-data="autoSubmit" @change="submit" class="flex w-full flex-wrap items-end gap-3 px-5 pb-4 pt-3 sm:px-6">
        @foreach (request()->except($own) as $name => $value)
            @if (is_scalar($value))<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endif
        @endforeach
        <div class="min-w-[12rem] flex-1">
            <label class="field-label" for="{{ $key }}-cari">Cari</label>
            <div class="flex gap-2">
                <input id="{{ $key }}-cari" type="search" name="{{ $qName }}" value="{{ request($qName) }}" class="input min-w-0 flex-1" placeholder="{{ $placeholder }}" autocomplete="off">
                <x-sort-by mode="server" :id="$sortId ?? $key.'-urut'" :name="$sortName" :dir-name="$dirName" :page-name="$paginator->getPageName()" :fragment="$key"
                           :default="$default" :dir="$dir" :options="$sorts" />
            </div>
        </div>
        @foreach ($filters as $name => $filter)
            <div>
                <label class="field-label" for="{{ $key }}-{{ $name }}">{{ $filter['label'] }}</label>
                <select id="{{ $key }}-{{ $name }}" name="{{ $key }}_{{ $name }}" class="input">
                    <option value="">Semua</option>
                    @foreach ($filter['options'] as $value => $label)
                        <option value="{{ $value }}" @selected((string) request("{$key}_{$name}") === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        @endforeach
        <noscript><button type="submit" class="btn btn-secondary">Terapkan</button></noscript>
    </form>

    @if ($paginator->isEmpty())
        <p class="border-t border-rule px-5 py-5 text-sm text-muted sm:px-6">{{ $filtered ? 'Tidak ada data yang cocok dengan pencarian / filter.' : $empty }}</p>
    @else
        <x-scroll-pager>{{ $slot }}</x-scroll-pager>
    @endif
    <x-server-pager :paginator="$paginator" :id="$key.'-per'" :per-page-name="$perPageName" :fragment="$key" :label="'Halaman '.$title"
                    :options="\App\Support\ProcessTable::PER_PAGE" />
</section>
