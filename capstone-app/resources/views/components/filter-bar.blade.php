{{-- Filter daftar admin tanpa tombol Terapkan/Reset: memilih opsi dropdown langsung menyaring (Alpine "autoSubmit"
     pada @change); kolom cari menyaring saat Enter ditekan atau kursor meninggalkan kolom. --}}
@props(['action'])
<form method="GET" action="{{ $action }}" x-data="autoSubmit" @change="submit" {{ $attributes->merge(['class' => 'flex w-full flex-wrap items-end gap-3']) }}>
    {{ $slot }}
    <noscript><button type="submit" class="btn btn-secondary">Terapkan</button></noscript>
</form>
