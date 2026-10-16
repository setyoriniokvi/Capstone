<x-layouts.app title="QR Code Tim" section="Mahasiswa · QR Code">
    <x-page-head title="QR Code tim" lede="Tunjukkan QR ini kepada dosen pembimbing atau penguji saat penilaian. Dosen tetap harus login dan hanya dapat menilai tim serta tahap yang menjadi kewenangannya." />
    @if (! $token)
        <div class="panel"><x-empty title="QR Code tersedia setelah registrasi tim diterima" icon="qr-code" /></div>
    @else
        <section class="panel mx-auto max-w-md">
            <div class="panel-body text-center">
                <p class="code text-lg font-medium text-brand-deep">{{ $team->code }}</p>
                <p class="text-muted">{{ $team->name }}</p>
                <div class="mx-auto my-6 w-full max-w-[16rem] rounded-lg border border-rule bg-white p-3 [&_svg]:h-auto [&_svg]:w-full">{!! $qr->svg($token) !!}</div>
                <a href="{{ route('mahasiswa.qr.download') }}" class="btn btn-secondary"><x-heroicon-o-arrow-down-tray class="h-5 w-5" />Unduh SVG</a>
                <p class="field-hint mt-4">QR hanya berisi kode acak. Identitas tim diverifikasi oleh sistem.</p>
            </div>
        </section>
    @endif
</x-layouts.app>
