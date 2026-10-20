{{-- Tab "Topik Tim": formulir topik mandiri (di atas, dibuka tombol "Registrasi Topik"/"Edit") → topik final & pembimbing
     → alur pengajuan topik → topik mandiri. Butuh $team, $independent, $canSubmitIndependent. --}}
@php
    $chip = 'rounded-md border border-rule bg-sunken px-2 py-0.5 text-caption font-medium text-muted';
    $phone = fn (?string $p) => $p ? preg_replace('/^(\d{4})(\d{4})(\d+)$/', '$1-$2-$3', $p) : null;
@endphp
@if (! $team || $team->status !== 'diterima')
    <div class="panel"><x-empty title="Pengajuan topik belum dibuka untuk tim Anda" icon="light-bulb">Topik dapat diajukan setelah registrasi tim diterima admin.</x-empty></div>
@else
    @php
        $final = $team->finalTopic;
        $supervisor = $team->supervisor?->lecturer;
        $accepted = $team->applications->firstWhere('status', 'diterima');
        $pending = $team->applications->firstWhere('status', 'menunggu');
        // Alur: done | current | upcoming
        $steps = [
            ['label' => 'Pengajuan Topik Mandiri Tim', 'state' => $independent?->status === 'diterima' ? 'done' : 'current'],
            ['label' => 'Pengajuan Topik Dosen (opsional)', 'state' => $accepted ? 'done' : ($pending ? 'current' : 'upcoming')],
            ['label' => 'Topik Final', 'state' => $final ? 'done' : 'upcoming'],
        ];
        $circle = ['done' => 'bg-brand text-white', 'current' => 'border-2 border-brand bg-surface text-brand-deep', 'upcoming' => 'border-2 border-rule-strong bg-surface text-muted'];
    @endphp
    <div x-data="registrationForm" data-open="{{ $errors->hasAny(['title', 'description', 'object']) ? 'true' : 'false' }}">
        @if ($canSubmitIndependent)
<x-modal>
    <x-slot:heading>{{ $independent ? 'Edit Topik Mandiri Tim' : 'Registrasi Topik Mandiri Tim' }}</x-slot:heading>
                <form method="POST" action="{{ route('mahasiswa.topic.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="field-label" for="team_name">Tim</label>
                        <input id="team_name" class="input bg-sunken text-muted" value="{{ $team->name }}" readonly aria-readonly="true">
                    </div>
                    <x-field label="Topik" name="title" required>
                        <input id="title" name="title" class="input" value="{{ old('title', $independent?->title) }}" required maxlength="255">
                    </x-field>
                    <x-field label="Deskripsi topik" name="description" required hint="Jelaskan konteks, problem owner, dan permasalahan sistem terintegrasi.">
                        <textarea id="description" name="description" rows="6" class="input" required>{{ old('description', $independent?->description) }}</textarea>
                    </x-field>
                    <x-field label="Objek Capstone" name="object" required hint="Dapat berupa perusahaan, instansi, atau objek lainnya.">
                        <input id="object" name="object" class="input" value="{{ old('object', $independent?->object) }}" required maxlength="255">
                    </x-field>
                    <div class="flex flex-wrap justify-end gap-3 border-t border-rule pt-4">
                        <button type="button" class="btn btn-ghost" @click="close">Batal</button>
                        <button class="btn btn-primary" data-once>{{ $independent ? ($independent->status === 'ditolak' ? 'Ajukan ulang' : 'Simpan perubahan') : 'Ajukan topik' }}</button>
                    </div>
                </form>
            </x-modal>
        @endif

        {{-- Topik final & dosen pembimbing (F25) — kartu hijau agar menonjol --}}
        <section x-data="disclosure" class="mb-6 rounded-lg bg-brand-deep text-on-brand shadow-panel" aria-labelledby="final-judul">
            <div class="grid gap-5 p-5 sm:p-6 md:grid-cols-[minmax(0,1fr)_17rem]">
                <div class="min-w-0">
                    <div class="flex items-start justify-between gap-3">
                        <p id="final-judul" class="text-sm text-on-brand-muted">Topik final {{ $team->name }}</p>
                        @if ($final)<span class="rounded-md border border-on-brand-line bg-white/10 px-2 py-0.5 text-caption font-medium text-on-brand">{{ $final->source === 'dosen' ? 'Topik Dosen' : 'Topik Mandiri Tim' }}</span>@endif
                    </div>
                    <p class="mt-1 text-h2 {{ $final ? 'text-on-brand' : 'text-on-brand-muted' }}">{{ $final?->title ?? 'Belum ditetapkan' }}</p>
                </div>
                <div class="border-t border-on-brand-line pt-4 md:border-l md:border-t-0 md:pl-6 md:pt-0">
                    <p class="text-sm text-on-brand-muted">Dosen pembimbing</p>
                    @if ($supervisor)
                        <p class="mt-1 font-medium">{{ $supervisor->name }}</p>
                        <p class="text-sm text-on-brand-muted"><a class="hover:text-on-brand" href="mailto:{{ $supervisor->email }}">{{ $supervisor->email }}</a></p>
                        @if ($supervisor->phone)<p class="text-sm text-on-brand-muted tabular">WA {{ $phone($supervisor->phone) }}</p>@endif
                    @else
                        <p class="mt-1 text-on-brand-muted">Belum ditetapkan</p>
                    @endif
                </div>
            </div>
            @if ($final)
                <div class="border-t border-on-brand-line px-5 sm:px-6">
                    <button type="button" class="inline-flex min-h-[44px] items-center gap-1.5 text-sm font-medium text-on-brand underline-offset-4 hover:underline" @click="toggle" :aria-expanded="expanded" aria-controls="detail-final">
                        <span x-show="collapsed" class="inline-flex items-center gap-1.5">Lihat detail <x-heroicon-o-chevron-down class="h-4 w-4" /></span>
                        <span x-show="expanded" x-cloak class="inline-flex items-center gap-1.5">Sembunyikan detail <x-heroicon-o-chevron-up class="h-4 w-4" /></span>
                    </button>
                    <dl id="detail-final" x-show="expanded" x-cloak class="grid gap-x-6 gap-y-3 pb-5 text-sm sm:grid-cols-[10rem_minmax(0,1fr)]">
                        <dt class="text-on-brand-muted">Deskripsi</dt><dd class="whitespace-pre-line">{{ $final->description }}</dd>
                        <dt class="text-on-brand-muted">Objek Capstone</dt><dd>{{ $final->object }}</dd>
                        @if ($final->source === 'dosen')
                            @if ($final->competencies)<dt class="text-on-brand-muted">Kompetensi</dt><dd class="whitespace-pre-line">{{ $final->competencies }}</dd>@endif
                            <dt class="text-on-brand-muted">Dosen pengusul</dt><dd>{{ $final->lecturer?->name }}</dd>
                        @endif
                    </dl>
                </div>
            @endif
        </section>

        {{-- Alur pengajuan topik --}}
        <section class="panel mb-6 p-5 sm:p-6" aria-labelledby="alur-judul">
            <h2 id="alur-judul" class="text-lg font-semibold text-ink">Alur Pengajuan Topik</h2>
            <ol class="mt-6 grid grid-cols-3">
                @foreach ($steps as $i => $step)
                    <li class="flex flex-col items-center text-center" @if ($step['state'] === 'current') aria-current="step" @endif>
                        <span class="relative flex h-9 w-full items-center justify-center" aria-hidden="true">
                            @unless ($loop->first)
                                <span class="absolute left-0 right-1/2 top-1/2 h-[3px] -translate-y-1/2 {{ $steps[$i - 1]['state'] === 'done' ? 'bg-brand' : 'bg-rule-strong' }}"></span>
                            @endunless
                            @unless ($loop->last)
                                <span class="absolute left-1/2 right-0 top-1/2 h-[3px] -translate-y-1/2 {{ $step['state'] === 'done' ? 'bg-brand' : 'bg-rule-strong' }}"></span>
                            @endunless
                            <span class="relative grid h-9 w-9 place-items-center rounded-full text-sm font-semibold tabular {{ $circle[$step['state']] }}">{{ $loop->iteration }}</span>
                        </span>
                        <span class="mt-2 max-w-[12rem] px-1 text-caption font-medium text-ink sm:text-sm">{{ $step['label'] }}<span class="sr-only"> — {{ ['done' => 'selesai', 'current' => 'berjalan', 'upcoming' => 'belum'][$step['state']] }}</span></span>
                    </li>
                @endforeach
            </ol>
        </section>

        {{-- Topik mandiri (F23) --}}
        <section x-data="disclosure" class="panel" aria-labelledby="mandiri-judul">
            <div class="flex flex-wrap items-center justify-between gap-3 px-5 pt-5 sm:px-6 sm:pt-6">
                <h2 id="mandiri-judul" class="text-lg font-semibold text-ink">Topik Mandiri Tim</h2>
                {{-- Pojok kanan atas: status topik, atau tombol registrasi bila belum ada topik --}}
                @if ($independent)
                    <x-badge :status="$independent->status" />
                @elseif ($canSubmitIndependent)
                    <button type="button" class="btn btn-primary" @click="open"><x-heroicon-o-plus class="h-5 w-5" /> Registrasi Topik</button>
                @endif
            </div>

            @if (! $independent)
                <p class="px-5 pb-6 pt-3 text-sm text-muted sm:px-6">Tim belum mengajukan Topik Mandiri Tim.</p>
            @else
                <div class="px-5 pb-2 pt-3 sm:px-6">
                    <p class="text-lg font-semibold leading-snug text-ink">{{ $independent->title }}</p>
                    @if ($independent->review_notes)
                        <p class="mt-3 rounded-lg border border-rule bg-sunken px-3 py-2 text-sm"><span class="font-medium">Catatan admin:</span> {{ $independent->review_notes }}</p>
                    @endif
                </div>
                <div class="mt-3 border-t border-rule px-5 sm:px-6">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <button type="button" class="link inline-flex min-h-[44px] items-center gap-1.5 text-sm" @click="toggle" :aria-expanded="expanded" aria-controls="detail-mandiri">
                            <span x-show="collapsed" class="inline-flex items-center gap-1.5">Lihat detail <x-heroicon-o-chevron-down class="h-4 w-4" /></span>
                            <span x-show="expanded" x-cloak class="inline-flex items-center gap-1.5">Sembunyikan detail <x-heroicon-o-chevron-up class="h-4 w-4" /></span>
                        </button>
                    </div>
                    <dl id="detail-mandiri" x-show="expanded" x-cloak class="grid gap-x-6 gap-y-3 pb-5 text-sm sm:grid-cols-[10rem_minmax(0,1fr)]">
                        <dt class="text-muted">Deskripsi</dt><dd class="whitespace-pre-line text-ink">{{ $independent->description }}</dd>
                        <dt class="text-muted">Objek Capstone</dt><dd class="text-ink">{{ $independent->object }}</dd>
                        <dt class="text-muted">Diajukan</dt><dd class="text-ink">{{ $independent->updated_at->translatedFormat('j F Y') }}</dd>
                        @if ($independent->reviewed_at)
                            <dt class="text-muted">Ditinjau</dt><dd class="text-ink">{{ $independent->reviewed_at->translatedFormat('j F Y') }}@if ($independent->reviewer) oleh {{ $independent->reviewer->name }}@endif</dd>
                        @endif
                    </dl>
                </div>
                {{-- Tetap tampil; dinonaktifkan setelah topik diterima admin --}}
                <div class="border-t border-rule px-5 py-4 sm:px-6">
                    <button type="button" class="btn btn-secondary disabled:cursor-not-allowed disabled:opacity-50" @click="open" @disabled(! $canSubmitIndependent)
                            @unless ($canSubmitIndependent) title="Topik yang sudah diterima tidak dapat diubah" @endunless>
                        <x-heroicon-o-pencil-square class="h-5 w-5" /> Edit topik
                    </button>
                    @unless ($canSubmitIndependent)<p class="mt-2 text-caption text-muted">Topik yang sudah diterima admin tidak dapat diubah.</p>@endunless
                </div>
            @endif
        </section>
    </div>
@endif
