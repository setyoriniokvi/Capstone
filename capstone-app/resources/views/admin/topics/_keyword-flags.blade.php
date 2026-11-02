{{-- Penanda kata kunci terlarang pada topik. Butuh $topic. --}}
@if ($topic->keyword_hits)
    <p class="mt-2 flex flex-wrap items-center gap-1.5 text-caption text-bad-ink">
        <x-heroicon-o-flag class="h-4 w-4" />Kata kunci terlarang:
        @foreach ($topic->keyword_hits as $hit)<span class="rounded bg-bad-tint px-1.5">{{ $hit }}</span>@endforeach
    </p>
@endif
