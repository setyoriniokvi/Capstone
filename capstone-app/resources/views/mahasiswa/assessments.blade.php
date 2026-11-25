{{-- F29 — Penilaian: nilai akhir per tahapan Capstone dari dosen (Proposal, Laporan Progress, Sidang, Pameran, …).
     Nilai akhir & indeks tidak ditampilkan ke mahasiswa; mahasiswa hanya melihat total nilai per asesmen.
     Tiap tahapan: nilai 0–100, penilai, dan feedback. Nilai tampil setelah dosen memfinalkan penilaiannya; draft dosen
     tidak ditampilkan. Butuh $team, $stages (ScoreCalculator::componentSummary + detail). --}}
<x-layouts.app title="Penilaian">
    <x-page-head title="Penilaian" />

    @if (! $team || $team->status !== 'diterima')
        <div class="panel"><x-empty title="Penilaian tersedia setelah registrasi tim diterima" icon="star" /></div>
    @elseif ($stages->isEmpty())
        <div class="panel"><x-empty title="Belum ada tahapan penilaian" icon="star">Admin belum mengatur indikator penilaian.</x-empty></div>
    @else
        @php
            $done = $stages->where('status', 'final')->count();
            $roleLabel = ['pembimbing' => 'Dosen pembimbing', 'penguji' => 'Dosen penguji'];
            $num = fn ($v) => number_format($v, 2, ',', '.');
        @endphp

        <section class="panel overflow-hidden" aria-labelledby="penilaian-judul">
            <div class="flex flex-col gap-3 border-b border-rule px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <h2 id="penilaian-judul" class="text-lg font-semibold text-ink">{{ $team->name }}</h2>
                <div class="w-full sm:w-64">
                    <p class="text-sm text-muted"><span class="tabular font-semibold text-ink">{{ $done }}/{{ $stages->count() }}</span> asesmen sudah dinilai</p>
                    <x-progress-bar class="mt-2" :value="$done" :max="$stages->count()" label="Asesmen sudah dinilai" />
                </div>
            </div>

            <ul class="divide-y divide-rule">
                @foreach ($stages as $stage)
                    @php $final = $stage['status'] === 'final'; @endphp
                    <li class="px-5 py-5 sm:px-6">
                        {{-- Layar ≥ sm: tiga kolom (kiri/kanan sama lebar) — status tepat di tengah baris, nilai di kanan; semua rata tengah vertikal --}}
                        <div class="flex flex-col gap-3 sm:grid sm:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] sm:items-center sm:gap-4">
                            <div class="min-w-0">
                                <p class="font-semibold text-ink">{{ $stage['label'] }}</p>
                                <p class="mt-1 text-sm text-muted">
                                    {{ $roleLabel[$stage['role']] ?? 'Dosen' }}{{ $stage['assessor'] ? ' - '.$stage['assessor']->name : ' - belum ditentukan' }}
                                    @if ($final && $stage['submitted_at']) - {{ $stage['submitted_at']->translatedFormat('j M Y') }}@endif
                                </p>
                            </div>
                            <x-badge class="self-start sm:self-auto sm:justify-self-center" :status="$final ? 'final' : 'belum'" :label="$final ? 'Sudah dinilai' : 'Belum dinilai'" />
                            {{-- Nilai tahapan (0–100) --}}
                            <p class="flex items-baseline gap-1 leading-none sm:justify-self-end">
                                @if ($final)<span class="tabular text-[2rem] font-bold tracking-tight text-brand">{{ $num($stage['score']) }}</span>@else<span class="tabular text-muted" title="Belum dinilai final">-</span>@endif
                                <span class="tabular text-muted">/ 100</span>
                            </p>
                        </div>
                        @if ($final && filled($stage['feedback']))
                            <div class="mt-4 rounded-lg border border-rule bg-sunken px-4 py-3 text-sm">
                                <p class="font-medium text-ink">Feedback dosen</p>
                                <p class="mt-1 whitespace-pre-line text-muted">{{ $stage['feedback'] }}</p>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</x-layouts.app>
