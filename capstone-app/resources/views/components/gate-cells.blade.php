{{-- Gate log bimbingan: N sel diskret, bukan progress bar generik (F27 / PRD 6.6). --}}
@props(['approved', 'min' => 8, 'pending' => 0])
<div {{ $attributes->merge(['class' => 'min-w-0']) }}>
    <div class="flex items-center gap-1" role="img" aria-label="{{ $approved }} dari {{ $min }} log bimbingan disetujui">
        @for ($i = 1; $i <= $min; $i++)
            <span class="h-6 flex-1 rounded-sm {{ $i <= $approved ? 'bg-brand' : ($i <= $approved + $pending ? 'border border-dashed border-warn bg-warn-tint' : 'border border-rule bg-sunken') }}"></span>
        @endfor
    </div>
    <p class="mt-2 text-sm">
        <span class="tabular font-medium">{{ $approved }}</span><span class="text-muted"> / {{ $min }} sesi disetujui</span>
        @if ($pending)
            <span class="text-muted"> · {{ $pending }} menunggu</span>
        @endif
        @if ($approved >= $min)
            <span class="ml-1 font-medium text-ok-ink">· syarat presentasi akhir terpenuhi</span>
        @endif
    </p>
</div>
