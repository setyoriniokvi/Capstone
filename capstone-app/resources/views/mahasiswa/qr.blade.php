<x-layouts.app title="QR Code Penilaian Pameran Tim" section="Mahasiswa · QR Code">
    <x-page-head title="QR Code Penilaian Pameran Tim" lede="Tunjukkan QR ini kepada dosen penguji saat pameran. Dosen dapat langsung masuk ke halaman penilaian pameran tim." />
    @if (! $token)
        <div class="panel"><x-empty title="QR Code tersedia setelah registrasi tim diterima" icon="qr-code" /></div>
    @else
        <section class="panel mx-auto max-w-md">
            <div class="panel-body text-center">
                <p class="text-lg font-semibold text-brand-deep">{{ $team->name }}</p>
                <div class="mx-auto my-6 w-full max-w-[16rem] rounded-lg border border-rule bg-white p-3 [&_svg]:h-auto [&_svg]:w-full">{!! $qr->svg($token) !!}</div>
                <div class="mx-auto flex max-w-[16rem] flex-col gap-2">
                    <form method="POST" action="{{ route('mahasiswa.qr.regenerate') }}" data-confirm="Buat QR Code baru? QR lama (termasuk yang sudah dicetak) tidak berlaku lagi." data-confirm-label="Buat QR baru" data-confirm-tone="positive">
                        @csrf
                        <button class="btn btn-secondary w-full"><x-heroicon-o-arrow-path class="h-5 w-5" />Generate QR baru</button>
                    </form>
                    <a href="{{ route('mahasiswa.qr.download') }}" class="btn btn-primary w-full"><x-heroicon-o-arrow-down-tray class="h-5 w-5" />Unduh PNG</a>
                </div>
                <p class="field-hint mt-4">QR hanya berisi kode acak. Identitas tim diverifikasi oleh sistem.</p>
            </div>
        </section>
    @endif
</x-layouts.app>
