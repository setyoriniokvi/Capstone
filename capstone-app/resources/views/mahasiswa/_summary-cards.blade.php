{{-- Tiga kartu ringkasan dashboard: log bimbingan, dokumen milestone, tenggat terdekat.
     Cukup angka + progress bar, tanpa kalimat keterangan; rincian ada di halaman masing-masing.
     Butuh $logbook, $documentSummary, $deadline (StudentDashboard). --}}
@php $docs = $documentSummary; @endphp
<div class="mb-6 grid gap-4 sm:grid-cols-3 sm:gap-5">
    <x-summary-card icon="clipboard-document-check" label="Log bimbingan disetujui">
        <p class="mt-3"><span class="tabular text-[1.75rem] font-bold leading-none tracking-tight">{{ $logbook['approved'] }}</span><span class="text-sm text-muted"> / {{ $logbook['min'] }} sesi</span></p>
        <div class="mt-auto pt-5">
            <x-progress-bar :value="$logbook['approved']" :max="$logbook['min']" label="Log bimbingan disetujui" />
        </div>
    </x-summary-card>

    <x-summary-card icon="document-text" label="Dokumen milestone">
        <p class="mt-3"><span class="tabular text-[1.75rem] font-bold leading-none tracking-tight">{{ $docs['done'] }}</span><span class="text-sm text-muted"> / {{ $docs['total'] }} terkumpul</span></p>
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
