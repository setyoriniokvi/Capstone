{{-- Filter daftar admin tanpa tombol Terapkan/Reset: memilih opsi dropdown langsung menyaring (Alpine "autoSubmit"
     pada @change); kolom cari menyaring saat Enter ditekan atau kursor meninggalkan kolom. Urutan (?urut, ?arah) dan jumlah
     data per halaman (?per_page) yang sedang dipakai ikut terkirim.
     except : (opsional) daftar parameter milik form ini — semua parameter lain di URL ikut terkirim apa adanya
              (halaman dengan beberapa kartu berfilter, mis. Master Data). --}}
@props(['action', 'except' => null])
<form method="GET" action="{{ $action }}" x-data="autoSubmit" @change="submit" {{ $attributes->merge(['class' => 'flex w-full flex-wrap items-end gap-3']) }}>
    @if (is_array($except))
        @foreach (request()->except($except) as $name => $value)
            @if (is_scalar($value))<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endif
        @endforeach
    @else
        @foreach (['urut', 'arah', 'per_page'] as $keep)
            @if (is_scalar(request($keep)) && request()->filled($keep))<input type="hidden" name="{{ $keep }}" value="{{ request($keep) }}">@endif
        @endforeach
    @endif
    {{ $slot }}
    <noscript><button type="submit" class="btn btn-secondary">Terapkan</button></noscript>
</form>
