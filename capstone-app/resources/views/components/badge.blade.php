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
    $text = $label ?? \Illuminate\Support\Str::of($status)->replace('_', ' ')->ucfirst();
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 whitespace-nowrap rounded px-2 py-0.5 text-caption font-medium {$classes}"]) }}>
    <span class="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true"></span>{{ $text }}
</span>
