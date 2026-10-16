@props(['title', 'lede' => null])
<div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div class="min-w-0">
        <h1 class="text-h1 text-ink">{{ $title }}</h1>
        @if ($lede)
            <p class="mt-1.5 max-w-[68ch] text-muted">{{ $lede }}</p>
        @endif
    </div>
    @if (isset($actions) && $actions->isNotEmpty())
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endif
</div>
