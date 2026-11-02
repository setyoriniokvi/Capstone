{{-- F29 — Penilaian: nilai akhir per tahapan Capstone dari dosen (Proposal, Laporan Progress, Sidang, Pameran, …).
     Atas: Nilai Akhir pribadi + indeks (A/AB/…/E, NAM) — ditetapkan setelah semua tahapan final & peer review lengkap.
     Tiap tahapan: nilai 0–100, penilai, dan feedback. Nilai tampil setelah dosen memfinalkan penilaiannya; draft dosen
     tidak ditampilkan. Butuh $team, $stages (ScoreCalculator::componentSummary + detail), $final. --}}
<x-layouts.app title="Penilaian">
    <x-page-head title="Penilaian" lede="Nilai akhir setiap tahapan Capstone dari dosen pembimbing dan penguji. Nilai berlaku sama untuk seluruh anggota tim dan tampil setelah dosen memfinalkan penilaiannya." />

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

        {{-- Nilai akhir pribadi (Σ nilai CLO: penilaian dosen + peer review) dan indeksnya; ditetapkan setelah semua komponen lengkap --}}
        <section class="panel mb-6 p-5 sm:p-6" aria-labelledby="nilai-akhir-judul">
            <h2 id="nilai-akhir-judul" class="text-lg font-semibold text-ink">Nilai Akhir</h2>
            @if ($final['complete'])
                <div class="mt-4 flex flex-wrap items-end gap-x-8 gap-y-4">
                    <div>
                        <p class="text-sm text-muted">Nilai</p>
                        <p class="leading-none"><span class="tabular text-[2.75rem] font-bold tracking-tight text-brand">{{ $num($final['total']) }}</span><span class="tabular text-muted"> / 100</span></p>
                    </div>
                    <div>
                        <p class="text-sm text-muted">Indeks</p>
                        <p class="leading-none"><span class="text-[2.75rem] font-bold tracking-tight text-ink">{{ $final['grade']['grade'] }}</span></p>
                    </div>
                    <div class="pb-1">
                        <p class="text-sm text-muted">Nilai Angka Mutu</p>
                        <p class="text-lg font-semibold text-ink"><span class="tabular">{{ number_format($final['grade']['point'], 1, ',', '.') }}</span> · {{ $final['grade']['label'] }}</p>
                    </div>
                </div>
            @else
                <p class="mt-3 flex items-center gap-3 rounded-lg border border-rule bg-sunken px-4 py-3 text-sm text-muted">
                    <x-heroicon-o-clock class="h-5 w-5 shrink-0" />
                    <span>Nilai akhir dan indeks ditetapkan setelah semua komponen penilaian lengkap. Menunggu: {{ implode(', ', $final['pending']) }}.</span>
                </p>
            @endif
        </section>

        <section class="panel overflow-hidden" aria-labelledby="penilaian-judul">
            <div class="flex flex-col gap-3 border-b border-rule px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <h2 id="penilaian-judul" class="text-lg font-semibold text-ink">{{ $team->name }}</h2>
                <div class="w-full sm:w-64">
                    <p class="text-sm text-muted"><span class="tabular font-semibold text-ink">{{ $done }}/{{ $stages->count() }}</span> tahapan sudah dinilai</p>
                    <x-progress-bar class="mt-2" :value="$done" :max="$stages->count()" label="Tahapan sudah dinilai" />
                </div>
            </div>

            <ul class="divide-y divide-rule">
                @foreach ($stages as $stage)
                    @php $final = $stage['status'] === 'final'; @endphp
                    <li class="px-5 py-5 sm:px-6">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <p class="flex flex-wrap items-center gap-2">
                                    <span class="font-semibold text-ink">{{ $stage['label'] }}</span>
                                    <x-badge :status="$final ? 'final' : 'belum'" :label="$final ? 'Sudah dinilai' : 'Belum dinilai'" />
                                </p>
                                <p class="mt-1 text-sm text-muted">
                                    {{ $roleLabel[$stage['role']] ?? 'Dosen' }}{{ $stage['assessor'] ? ' · '.$stage['assessor']->name : ' · belum ditentukan' }}
                                    @if ($final && $stage['submitted_at']) · {{ $stage['submitted_at']->translatedFormat('j M Y') }}@endif
                                </p>
                            </div>
                            {{-- Nilai tahapan (0–100) --}}
                            <div class="shrink-0 sm:text-right">
                                <p class="leading-none">
                                    <span class="tabular text-[2rem] font-bold tracking-tight {{ $final ? 'text-brand' : 'text-faint' }}">{{ $final ? $num($stage['score']) : '—' }}</span>
                                    <span class="tabular text-muted"> / 100</span>
                                </p>
                            </div>
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
