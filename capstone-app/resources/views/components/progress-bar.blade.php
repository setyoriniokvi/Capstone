{{-- Bilah progres membulat: lintasan abu-abu, isian hijau. Persentase dibatasi 0–100.
     tone: brand (hijau FRI) | accent (hijau toska) --}}
@props(['value', 'max', 'label', 'tone' => 'brand'])
@php $percent = $max > 0 ? min(100, max(0, round($value / $max * 100))) : 0; @endphp
<div {{ $attributes->merge(['class' => 'h-2 overflow-hidden rounded-full bg-rule']) }}
     role="progressbar" aria-label="{{ $label }}" aria-valuemin="0" aria-valuemax="{{ $max }}" aria-valuenow="{{ min($value, $max) }}">
    <div class="h-full rounded-full {{ $tone === 'accent' ? 'bg-accent' : 'bg-brand' }}" style="width: {{ $percent }}%"></div>
</div>
