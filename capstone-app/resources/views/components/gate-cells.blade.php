{{-- Gate log bimbingan (F27 / PRD 6.6): bilah progres yang sama dengan halaman Log Bimbingan mahasiswa. --}}
@props(['approved', 'min' => 8, 'pending' => 0])
<div {{ $attributes->merge(['class' => 'min-w-0']) }}>
    <x-progress-bar :value="$approved" :max="$min" label="{{ $approved }} dari {{ $min }} log bimbingan disetujui" />
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
