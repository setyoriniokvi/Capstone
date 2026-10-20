@props(['status', 'label' => null])
@php
    // PRD §10.3 — pending kuning, approved hijau, rejected merah, konsisten di semua modul.
    $tone = match ($status) {
        'diterima', 'disetujui', 'berhasil', 'terkirim', 'final', 'lengkap', 'aktif', 'OK', 'tersedia' => 'ok',
        'menunggu', 'diundang', 'dikumpulkan', 'antre', 'proses', 'diproses', 'draft', 'UNDER', 'parsial' => 'warn',
        'ditolak', 'revisi', 'gagal', 'OVER', 'penuh', 'terlambat' => 'bad',
        default => 'neutral',
    };
    $classes = [
        'ok' => 'bg-ok-tint text-ok-ink',
        'warn' => 'bg-warn-tint text-warn-ink',
        'bad' => 'bg-bad-tint text-bad-ink',
        'neutral' => 'bg-sunken text-muted',
    ][$tone];
    // Ikon membawa status selain warna (aksesibilitas buta warna).
    $icon = ['ok' => 'check-circle', 'warn' => 'clock', 'bad' => 'x-circle', 'neutral' => 'minus-circle'][$tone];
    $text = $label ?? \Illuminate\Support\Str::of($status)->replace('_', ' ')->ucfirst();
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 whitespace-nowrap rounded-md px-2 py-0.5 text-caption font-medium {$classes}"]) }}>
    <x-dynamic-component :component="'heroicon-m-'.$icon" class="h-4 w-4 shrink-0" aria-hidden="true" />{{ $text }}
</span>
