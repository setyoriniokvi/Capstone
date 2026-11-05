{{-- F28 — Peer review. Kartu tim: nama tim + indikator "x/y Anggota Tim dinilai"; daftar rekan dengan status
     Dinilai/Belum Dinilai dan tombol "Nilai" yang membuka panel penilaian 6 aspek (rubrik config/peer_rubric.php).
     Tiap aspek dinilai dengan skala 1–5 (tombol bulat ala Google Form) berikon wajah (Alpine "peerSlider"): 1–2 cemberut, 3 datar, 4–5 senyum. --}}
<x-layouts.app title="Peer Review">
    <x-page-head title="Peer review" lede="Nilai rekan satu tim pada {{ count($aspects) }} aspek kerja tim (skala 1–5). Peer review menyumbang 10% nilai akhir (CLO5). Terbuka selama periode berlangsung dan dapat diubah kapan saja." />

    @if (! $team || $team->status !== 'diterima')
        <div class="panel"><x-empty title="Peer review tersedia setelah tim diterima" icon="users" /></div>
    @elseif ($teammates->isEmpty())
        <div class="panel"><x-empty title="Tidak ada rekan satu tim untuk dinilai" icon="users" /></div>
    @else
        @php
            $done = $teammates->filter(fn ($m) => $reviewed->contains($m->id))->count();
        @endphp

        <section class="panel" aria-labelledby="tim-judul">
            {{-- Nama tim + indikator anggota dinilai --}}
            <div class="flex flex-col gap-3 border-b border-rule px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <h2 id="tim-judul" class="text-lg font-semibold text-ink">{{ $team->name }}</h2>
                <div class="w-full sm:w-64">
                    <p class="text-sm text-muted"><span class="tabular font-semibold text-ink">{{ $done }}/{{ $teammates->count() }}</span> Anggota Tim dinilai</p>
                    <x-progress-bar class="mt-2" :value="$done" :max="$teammates->count()" label="Anggota tim dinilai" />
                </div>
            </div>

            <ul class="divide-y divide-rule">
                @foreach ($teammates as $mate)
                    @php
                        $isDone = $reviewed->contains($mate->id);
                        $mine = old('reviewee_id') == $mate->id;
                    @endphp
                    <li x-data="registrationForm" data-open="{{ $mine && $errors->any() ? 'true' : 'false' }}" class="px-5 py-4 sm:px-6">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-brand-tint text-sm font-semibold text-brand-deep" aria-hidden="true">{{ $mate->initials() }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-ink">{{ $mate->name }}</p>
                                <p class="code text-sm text-muted">{{ $mate->nim }}</p>
                            </div>
                            <x-badge :status="$isDone ? 'diterima' : 'menunggu'" :label="$isDone ? 'Dinilai' : 'Belum Dinilai'" />
                            <button type="button" class="btn btn-secondary btn-sm" @click="toggle" :aria-expanded="expanded" aria-controls="nilai-{{ $mate->id }}">
                                {{ $isDone ? 'Ubah nilai' : 'Nilai' }}
                            </button>
                        </div>

                        <x-modal width="sm:max-w-3xl">
                        <x-slot:heading>Nilai {{ $mate->name }}</x-slot:heading>
                        <form id="nilai-{{ $mate->id }}" method="POST" action="{{ route('mahasiswa.peer.store') }}">
                            @csrf
                            <input type="hidden" name="reviewee_id" value="{{ $mate->id }}">
                            {{-- Tiap aspek dipisah garis agar tidak tertukar --}}
                            <div class="divide-y divide-rule [&>*]:py-6 [&>*:first-child]:pt-0 [&>*:last-child]:pb-0">
                                @foreach ($aspects as $aspect => $label)
                                    @php
                                        $value = (int) ($mine ? old("scores.{$aspect}") : null) ?: (int) ($existing->get($mate->id)?->get($aspect) ?? 3);
                                        $levelNames = [1 => 'Sangat buruk', 2 => 'Buruk', 3 => 'Cukup', 4 => 'Baik', 5 => 'Sangat baik'];
                                    @endphp
                                    <div>{{-- pembungkus: garis pemisah di atas tiap aspek (legend fieldset tidak ikut "menumpang" di garis) --}}
                                    <fieldset x-data="peerSlider" data-value="{{ $value }}">
                                        <legend class="font-semibold text-ink">{{ $label }}</legend>
                                        <p class="mt-0.5 text-sm text-muted">{{ $rubric[$aspect]['definition'] }}</p>

                                        <div class="mt-3 flex items-center gap-4">
                                            {{-- Wajah berubah mengikuti nilai --}}
                                            <svg viewBox="0 0 40 40" class="h-12 w-12 shrink-0" aria-hidden="true">
                                                <circle cx="20" cy="20" r="18" class="transition-colors duration-300" :class="faceClass" />
                                                <circle cx="14" cy="16" r="2" class="fill-ink" />
                                                <circle cx="26" cy="16" r="2" class="fill-ink" />
                                                <path class="fill-none stroke-ink" stroke-width="2.5" stroke-linecap="round" :style="mouthStyle" />
                                            </svg>
                                            {{-- Skala linear 1–5 ala Google Form: 1 (kiri) = sangat buruk, 5 (kanan) = sangat baik --}}
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-end gap-2 sm:gap-4">
                                                    <span class="hidden pb-2.5 text-caption text-muted sm:block">Sangat buruk</span>
                                                    <div class="grid flex-1 grid-cols-5">
                                                        @foreach (range(1, 5) as $s)
                                                            <label class="flex cursor-pointer flex-col items-center gap-1.5 rounded-lg py-1 hover:bg-sunken">
                                                                <span class="text-sm font-medium tabular text-ink" aria-hidden="true">{{ $s }}</span>
                                                                <input type="radio" name="scores[{{ $aspect }}]" value="{{ $s }}" @checked($value === $s) @change="change"
                                                                       class="h-5 w-5 cursor-pointer accent-brand">
                                                                <span class="sr-only">{{ $s }} — {{ $levelNames[$s] }}</span>
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                    <span class="hidden pb-2.5 text-caption text-muted sm:block">Sangat baik</span>
                                                </div>
                                                <div class="mt-1 flex justify-between text-caption text-muted sm:hidden"><span>Sangat buruk</span><span>Sangat baik</span></div>
                                            </div>
                                        </div>
                                    </fieldset>
                                    </div>
                                @endforeach
                            </div>
                            @if ($mine)
                                @error('scores.*')<p class="field-error mt-3">{{ $message }}</p>@enderror
                            @endif
                            <div class="mt-5 flex flex-wrap justify-end gap-3 border-t border-rule pt-4">
                                <button type="button" class="btn btn-ghost" @click="close">Batal</button>
                                <button class="btn btn-primary" data-once>Simpan penilaian</button>
                            </div>
                        </form>
                        </x-modal>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</x-layouts.app>
