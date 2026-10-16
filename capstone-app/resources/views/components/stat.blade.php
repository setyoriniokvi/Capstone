@props(['label', 'value', 'href' => null, 'tone' => null])
@php $tag = $href ? 'a' : 'div'; @endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'block min-w-0 px-4 py-3 '.($href ? 'hover:bg-brand-wash' : '')]) }}>
    <p class="text-sm text-muted">{{ $label }}</p>
    <p class="mt-1 font-mono text-2xl font-medium tabular {{ $tone === 'alert' && $value > 0 ? 'text-bad-ink' : 'text-ink' }}">{{ $value }}</p>
</{{ $tag }}>
