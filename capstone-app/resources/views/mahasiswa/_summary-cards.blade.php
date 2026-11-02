{{-- Tiga kartu ringkasan dashboard: log bimbingan, dokumen milestone, deadline terdekat.
     Cukup angka + progress bar, tanpa kalimat keterangan; rincian ada di halaman masing-masing.
     Butuh $team, $logbook, $documentSummary, $deadline (StudentDashboard). Belum bertim: kartu tetap tampil
     dengan progress kosong dan keterangan "belum memiliki tim". --}}
@php
    $docs = $documentSummary;
    $minLogbooks = $logbook['min'] ?? config('capstone.min_approved_logbooks');
@endphp
<div class="mb-6 grid gap-4 sm:grid-cols-3 sm:gap-5">
    <x-summary-card icon="clipboard-document-check" label="Log bimbingan disetujui">
        @if ($team)
            <p class="mt-3"><span class="tabular text-[1.75rem] font-bold leading-none tracking-tight">{{ $logbook['approved'] }}</span><span class="text-sm text-muted"> / {{ $minLogbooks }} sesi</span></p>
        @else
            <p class="mt-3 text-sm text-muted">Anda belum memiliki tim. Log bimbingan dapat diisi setelah tim terbentuk dan pembimbing ditetapkan.</p>
        @endif
        <div class="mt-auto pt-5">
            <x-progress-bar :value="$logbook['approved'] ?? 0" :max="$minLogbooks" label="Log bimbingan disetujui" />
        </div>
    </x-summary-card>

    <x-summary-card icon="document-text" label="Dokumen milestone">
        @if ($team)
            <p class="mt-3"><span class="tabular text-[1.75rem] font-bold leading-none tracking-tight">{{ $docs['done'] }}</span><span class="text-sm text-muted"> / {{ $docs['total'] }} terkumpul</span></p>
        @else
            <p class="mt-3 text-sm text-muted">Anda belum memiliki tim. Dokumen milestone dikumpulkan per tim.</p>
        @endif
        <div class="mt-auto pt-5">
            <x-progress-bar :value="$docs['done']" :max="$docs['total']" label="Dokumen milestone terkumpul" tone="accent" />
        </div>
    </x-summary-card>

    <x-summary-card icon="clock" label="Deadline terdekat" :href="route('mahasiswa.documents.index')" link-label="Lihat semua deadline di halaman dokumen">
        @if ($deadline)
            @php $due = $deadline['milestone']->due_at; @endphp
            <time datetime="{{ $due->toIso8601String() }}" class="mt-3 block">
                <span class="block tabular text-[1.75rem] font-bold leading-tight tracking-tight">{{ $due->translatedFormat('j F Y') }}</span>
                <span class="mt-1 block tabular text-sm text-muted">Pukul {{ $due->format('H:i') }}</span>
            </time>
        @else
            <p class="mt-3 text-sm text-muted">Tidak ada deadline mendatang.</p>
        @endif
    </x-summary-card>
</div>
