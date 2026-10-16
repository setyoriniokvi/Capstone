@props(['title', 'icon' => 'inbox'])
<div {{ $attributes->merge(['class' => 'px-5 py-10 text-center']) }}>
    <x-dynamic-component :component="'heroicon-o-'.$icon" class="mx-auto h-8 w-8 text-faint" />
    <p class="mt-3 font-medium text-ink">{{ $title }}</p>
    @if ($slot->isNotEmpty())
        <div class="mx-auto mt-1 max-w-[52ch] text-sm text-muted">{{ $slot }}</div>
    @endif
</div>
