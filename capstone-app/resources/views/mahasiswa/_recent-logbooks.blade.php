{{-- Tiga log bimbingan terbaru, hanya daftar (baris tidak dapat diklik); "Lihat semua" membuka menu Log Bimbingan. Butuh $recentLogbooks dan $supervisor (StudentDashboard).
     Alasan log belum bisa diisi sudah tampil di kartu ringkasan, jadi tidak diulang di sini. --}}
<section class="panel mb-6" aria-labelledby="log-judul">
    <div class="flex flex-wrap items-center justify-between gap-x-3 px-5 pt-5 sm:px-6 sm:pt-6">
        <h2 id="log-judul" class="min-w-0 text-lg font-semibold">Log Bimbingan</h2>
        <a href="{{ route('mahasiswa.logbooks.index') }}" class="link -my-2 inline-flex min-h-[44px] items-center gap-1.5 text-sm">{{ $recentLogbooks->isEmpty() && $supervisor ? 'Isi log pertama' : 'Lihat semua' }} <x-heroicon-o-arrow-right class="h-4 w-4" /></a>
    </div>

    @if ($recentLogbooks->isEmpty())
        <p class="px-5 pb-5 pt-3 text-sm text-muted sm:px-6">Belum ada log bimbingan.</p>
    @else
        <ul class="mx-5 mt-3 divide-y divide-rule border-t border-rule pb-2 sm:mx-6">
            @foreach ($recentLogbooks as $log)
                @php $statusLabel = ['menunggu' => 'Menunggu', 'disetujui' => 'Disetujui', 'revisi' => 'Ditolak'][$log->status] ?? null; @endphp
                <li class="flex items-center gap-4 py-3">
                    {{-- Kolom tanggal tipografis (bukan ubin "S9") --}}
                    <time datetime="{{ $log->session_date->toDateString() }}" class="w-12 shrink-0 text-center leading-tight">
                        <span class="block tabular text-lg font-semibold text-ink">{{ $log->session_date->format('j') }}</span>
                        <span class="block text-caption text-muted">{{ $log->session_date->translatedFormat('M') }}</span>
                    </time>
                    <span class="min-w-0 flex-1 border-l border-rule pl-4">
                        <span class="block truncate text-sm font-medium text-ink">{{ $log->activity }}</span>
                        <span class="block text-caption text-muted">Sesi {{ $log->session_no }}</span>
                        {{-- Layar sempit: status di bawah judul agar judul tidak terpotong habis --}}
                        <x-badge :status="$log->status" :label="$statusLabel" class="mt-1.5 xs:hidden" />
                    </span>
                    <x-badge :status="$log->status" :label="$statusLabel" class="hidden shrink-0 xs:inline-flex" />
                </li>
            @endforeach
        </ul>
    @endif
</section>
