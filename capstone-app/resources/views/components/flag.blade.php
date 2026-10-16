@props(['value'])
@php
    $map = ['OVER' => 'bg-bad-tint text-bad-ink', 'UNDER' => 'bg-warn-tint text-warn-ink', 'OK' => 'bg-ok-tint text-ok-ink'];
@endphp
<span class="inline-block rounded px-1.5 py-0.5 font-mono text-[0.6875rem] font-medium {{ $map[$value] ?? 'bg-sunken text-muted' }}">{{ $value }}</span>
