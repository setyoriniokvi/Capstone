@php
    $editing = $period->exists;
    $dt = fn ($v) => $v ? $v->format('Y-m-d\TH:i') : '';
    // Bentuk penilaian dosen (peer review diisi mahasiswa, tidak dijadwalkan di sini) — disiapkan PeriodController.
    $components = $lecturerComponents;
    // Periode baru: label untuk daftar milestone draf (Alpine "periodDraft")
    $draftLabels = ['categories' => config('capstone.milestone_categories'), 'documents' => config('capstone.document_types'),
        'files' => config('capstone.document_file_types'), 'kinds' => config('capstone.milestone_kinds')];
    $draftError = 'mb-4 rounded-lg border border-bad-line bg-bad-tint px-4 py-3 text-sm text-bad-ink';
    // Jenis dokumen yang dinilai tiap bentuk penilaian (penghubung tombol Nilai di Deliverable)
    $docTypeLabels = fn ($types) => collect($types ?? [])->map(fn ($t) => config("capstone.document_types.$t", $t))->implode(', ') ?: '—';
    $docTypes = $editing ? $period->milestones->where('kind', 'dokumen')->pluck('document_type')->filter()->unique()->values() : collect();
@endphp
<x-layouts.app :title="$editing ? $period->name : 'Periode baru'">
    <x-page-head :title="$editing ? $period->name : 'Periode baru'">
        <x-slot:actions>
            <a href="{{ route('admin.periods.index') }}" class="btn btn-ghost">Kembali</a>
            @if ($editing)
                <form method="POST" action="{{ route('admin.workspace.enter', $period) }}">@csrf<button class="btn btn-primary">Buka dashboard periode</button></form>
            @endif
            @if ($editing && ! $period->is_active)
                <form method="POST" action="{{ route('admin.periods.activate', $period) }}">@csrf<button class="btn btn-secondary">Jadikan aktif</button></form>
            @endif
        </x-slot:actions>
    </x-page-head>

    {{-- Isian periode berada di beberapa kartu; kartu Milestone (form sendiri) disisipkan di antaranya, jadi isian memakai atribut form="period-form". --}}
    <form id="period-form" method="POST" action="{{ $editing ? route('admin.periods.update', $period) : route('admin.periods.store') }}">
        @csrf
        @if ($editing) @method('PUT') @endif
    </form>

    {{-- Periode baru: milestone, jadwal penilaian, dan folder Drive disusun langsung di halaman ini (Alpine "periodDraft")
         dan tersimpan bersama tombol "Buat Periode" — tidak perlu menyimpan periode terlebih dahulu. --}}
    <div class="space-y-6" @unless ($editing) x-data="periodDraft" data-labels="{{ json_encode($draftLabels) }}"
         data-old-milestones="{{ old('draft_milestones', '[]') }}" data-old-folders="{{ json_encode((object) old('drive_folders', [])) }}" @endunless>
        @unless ($editing)
            <input type="hidden" form="period-form" name="draft_milestones" value="{{ old('draft_milestones', '[]') }}">
            <template data-doc-icon><x-heroicon-s-document-text class="h-2 w-2 sm:h-2.5 sm:w-2.5" /></template>
        @endunless
        <section class="panel">
            <div class="panel-head"><h2 class="panel-title">Identitas periode</h2></div>
            <div class="panel-body grid gap-4 sm:grid-cols-2">
                <x-field label="Nama periode" name="name" required class="sm:col-span-2">
                    <input id="name" form="period-form" name="name" class="input" value="{{ old('name', $period->name) }}" placeholder="Capstone Ganjil 2026/2027" required>
                </x-field>
                <x-field label="Tahun akademik" name="academic_year" required>
                    <input id="academic_year" form="period-form" name="academic_year" class="input tabular" value="{{ old('academic_year', $period->academic_year) }}" required>
                </x-field>
                <x-field label="Semester" name="semester" required>
                    <select id="semester" form="period-form" name="semester" class="input">
                        @foreach (['ganjil' => 'Ganjil', 'genap' => 'Genap'] as $v => $l)
                            <option value="{{ $v }}" @selected(old('semester', $period->semester) === $v)>{{ $l }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field label="Nama prodi" name="prodi_name" required class="sm:col-span-2">
                    <input id="prodi_name" form="period-form" name="prodi_name" class="input" value="{{ old('prodi_name', $period->prodi_name) }}" placeholder="S1 Sistem Informasi" required maxlength="150">
                </x-field>
                <x-field label="Tanggal mulai (minggu ke-1)" name="starts_on" required>
                    <input type="date" id="starts_on" form="period-form" name="starts_on" class="input" value="{{ old('starts_on', $period->starts_on?->format('Y-m-d')) }}" required @unless ($editing) @input="dates" @change="dates" @endunless>
                </x-field>
                <x-field label="Tanggal selesai" name="ends_on" required>
                    <input type="date" id="ends_on" form="period-form" name="ends_on" class="input" value="{{ old('ends_on', $period->ends_on?->format('Y-m-d')) }}" required @unless ($editing) @input="dates" @change="dates" @endunless>
                </x-field>
                @unless ($editing)
                    {{-- Setiap periode punya data master sendiri; periode baru memulai dari salinan. --}}
                    <x-field label="Salin data master dari" name="master_source" class="sm:col-span-2"
                             hint="Bentuk penilaian, CLO, indikator, rubrik dokumen/lisan/peer review, reminder otomatis, serta instruksi & kata kunci topik disalin ke periode baru. Memilih periode juga menyalin daftar dosennya. File pendukung tidak disalin.">
                        <select id="master_source" form="period-form" name="master_source" class="input" @change="pickSource">
                            <option value="">Data master bawaan (rubrik standar)</option>
                            @foreach ($sources ?? [] as $source)
                                <option value="{{ $source->id }}" @selected((string) old('master_source') === (string) $source->id)>{{ $source->name }} · {{ $source->prodi_name }} ({{ $source->label() }})</option>
                            @endforeach
                        </select>
                    </x-field>
                @endunless
            </div>
        </section>


        {{-- F5 — aturan registrasi tim untuk seluruh periode (bukan per prodi) --}}
        @php
            $courseLines = old('rule.courses', $rule?->courses->map(fn ($c) => $c->code.' - '.$c->name)->implode("
"));
            $specLines = old('rule.specializations', $rule?->specializations->pluck('name')->implode("
"));
            $classLines = old('rule.classes', implode("
", $rule?->classes ?? []));
        @endphp
        <section id="aturan-registrasi" class="panel scroll-mt-20">
            <div class="panel-head">
                <h2 class="panel-title">Aturan registrasi tim</h2>
                <p class="text-sm text-muted">Dasar screening otomatis registrasi tim pada periode ini.</p>
            </div>
            <div class="panel-body grid gap-4 sm:grid-cols-3">
                <x-field label="Minimal SKS lulus" name="rule.min_sks" required>
                    <input form="period-form" id="rule.min_sks" type="number" min="0" max="160" name="rule[min_sks]" class="input tabular" value="{{ old('rule.min_sks', $rule->min_sks ?? 110) }}" required>
                </x-field>
                <x-field label="Minimal anggota" name="rule.min_members" required>
                    <input form="period-form" id="rule.min_members" type="number" min="1" max="10" name="rule[min_members]" class="input tabular" value="{{ old('rule.min_members', $rule->min_members ?? config('capstone.team_size.min')) }}" required>
                </x-field>
                <x-field label="Maksimal anggota" name="rule.max_members" required>
                    <input form="period-form" id="rule.max_members" type="number" min="1" max="10" name="rule[max_members]" class="input tabular" value="{{ old('rule.max_members', $rule->max_members ?? config('capstone.team_size.max')) }}" required>
                </x-field>
                <div class="space-y-1 sm:col-span-3">
                    <input form="period-form" type="hidden" name="rule[require_distinct_region]" value="0">
                    <label class="flex min-h-[44px] items-center gap-3">
                        <input form="period-form" type="checkbox" class="checkbox" name="rule[require_distinct_region]" value="1" @checked(old('rule.require_distinct_region', $rule->require_distinct_region ?? true))>
                        <span>Wajib minimal satu anggota berbeda daerah asal <span class="text-muted">(multikultural)</span></span>
                    </label>
                    <input form="period-form" type="hidden" name="rule[require_distinct_specialization]" value="0">
                    <label class="flex min-h-[44px] items-center gap-3">
                        <input form="period-form" type="checkbox" class="checkbox" name="rule[require_distinct_specialization]" value="1" @checked(old('rule.require_distinct_specialization', $rule->require_distinct_specialization ?? true))>
                        <span>Wajib minimal satu anggota berbeda peminatan <span class="text-muted">(multidisiplin)</span></span>
                    </label>
                </div>
                <x-field label="Mata kuliah prasyarat / micro-course" name="rule.courses" class="sm:col-span-3 lg:col-span-2" hint="Satu per baris dengan format KODE - Nama, mis. KP - Kerja Praktek. Kode dicocokkan dengan kolom mk_lulus data mahasiswa.">
                    <textarea form="period-form" id="rule.courses" name="rule[courses]" rows="4" class="input font-mono text-sm" placeholder="KP - Kerja Praktek">{{ $courseLines }}</textarea>
                </x-field>
                <x-field label="Peminatan" name="rule.specializations" class="sm:col-span-3 lg:col-span-1" hint="Satu per baris. Dipilih dosen sebagai peminatan yang diharapkan pada topik.">
                    <textarea form="period-form" id="rule.specializations" name="rule[specializations]" rows="4" class="input text-sm" placeholder="Enterprise Data Management">{{ $specLines }}</textarea>
                </x-field>
                <x-field label="Kelas" name="rule.classes" class="sm:col-span-3 lg:col-span-1" hint="Satu per baris, mis. SI-47-01. Dipilih mahasiswa saat registrasi akun & tim.">
                    <textarea form="period-form" id="rule.classes" name="rule[classes]" rows="4" class="input font-mono text-sm" placeholder="SI-47-01">{{ $classLines }}</textarea>
                </x-field>
            </div>
        </section>

        @if ($editing)
        <x-capstone-timeline :period="$period" :list="false" />

        <section class="panel">
            <div class="panel-head">
                <h2 class="panel-title">Milestone</h2>
                <span class="text-sm text-muted">{{ $period->milestones->count() }} milestone</span>
            </div>
            @if ($period->milestones->isNotEmpty())
                <ul class="divide-y divide-rule">
                    @foreach ($period->milestones as $m)
                        <li x-data="disclosure" class="px-4 py-3 sm:px-6">
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                                <span class="w-14 shrink-0 tabular text-sm text-muted">Mg {{ $period->weekOfMilestone($m) }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="font-medium">{{ $m->name }}</p>
                                    <p class="text-sm text-muted"><span class="mr-1 rounded-md border border-rule bg-sunken px-1.5 py-0.5 text-caption font-medium">{{ config('capstone.milestone_categories.'.$m->category()) }}</span> @if ($m->document_type){{ config('capstone.document_types.'.$m->document_type) }} ({{ $m->fileTypesLabel() }})@else{{ $m->kindLabel() }}@endif · {{ $m->starts_at->translatedFormat('j M H:i') }} → {{ $m->due_at->translatedFormat('j M Y H:i') }}</p>
                                </div>
                                <button type="button" class="btn btn-secondary btn-sm" @click="toggle" :aria-expanded="expanded">Ubah</button>
                                <form method="POST" action="{{ route('admin.milestones.destroy', $m) }}" data-confirm="Hapus milestone “{{ $m->name }}”?">@csrf @method('DELETE')
                                    <button class="btn btn-ghost btn-sm text-bad-ink" aria-label="Hapus {{ $m->name }}">Hapus</button>
                                </form>
                            </div>
                            <x-modal width="sm:max-w-3xl">
                                <x-slot:heading>Ubah milestone · {{ $m->name }}</x-slot:heading>
                                @include('admin.periods._milestone-fields', ['m' => $m, 'action' => route('admin.milestones.update', $m), 'method' => 'PUT', 'submit' => 'Simpan'])
                            </x-modal>
                        </li>
                    @endforeach
                </ul>
            @endif
            {{-- Tambah milestone lewat modal di atas halaman; terbuka lagi bila simpan gagal validasi. --}}
            <div x-data="registrationForm" data-open="{{ $errors->hasAny(['category', 'kind', 'document_type', 'allowed_types', 'starts_at', 'due_at']) && old('_method') !== 'PUT' && ! old('_component') ? 'true' : 'false' }}"
                 class="border-t border-rule px-4 py-4 sm:px-6">
                <button type="button" class="btn btn-secondary" @click="open" :aria-expanded="expanded">
                    <x-heroicon-o-plus class="h-5 w-5" /> Tambah Milestone
                </button>
                <x-modal width="sm:max-w-3xl">
                    <x-slot:heading>Tambah milestone</x-slot:heading>
                    @include('admin.periods._milestone-fields', ['m' => new \App\Models\Milestone(['kind' => 'dokumen']), 'action' => route('admin.milestones.store', $period), 'method' => 'POST', 'submit' => 'Tambah'])
                </x-modal>
            </div>
        </section>
        @else
        {{-- Periode baru: Timeline muncul setelah tanggal mulai & selesai diisi; milestone disusun di browser --}}
        <section class="panel min-w-0 p-5 sm:p-6" x-show="hasRange" x-cloak aria-labelledby="timeline-draf">
            <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                <h2 id="timeline-draf" class="text-lg font-semibold text-ink">Timeline Capstone</h2>
                <p class="tabular text-sm text-muted" x-text="rangeLabel"></p>
            </div>
            <ol data-draft-timeline class="mt-6 grid"></ol>
        </section>

        <section class="panel">
            <div class="panel-head">
                <h2 class="panel-title">Milestone</h2>
                <span class="text-sm text-muted" x-text="milestoneCount"></span>
            </div>
            @error('draft_milestones')<p class="{{ $draftError }} mx-4 mt-4 sm:mx-6">{{ $message }}</p>@enderror
            <ul data-draft-list class="divide-y divide-rule" @click="listClick" hidden></ul>
            <div class="border-t border-rule px-4 py-4 sm:px-6">
                <button type="button" class="btn btn-secondary disabled:cursor-not-allowed disabled:opacity-50" @click="newMilestone" :disabled="noRange">
                    <x-heroicon-o-plus class="h-5 w-5" /> Tambah Milestone
                </button>
                <p x-show="noRange" class="mt-2 text-caption text-muted">Isi tanggal mulai dan tanggal selesai periode untuk menambah milestone.</p>
            </div>
            <x-modal width="sm:max-w-3xl">
                <x-slot:heading><span x-text="milestoneHeading"></span></x-slot:heading>
                @include('admin.periods._milestone-fields', ['m' => new \App\Models\Milestone(['kind' => 'dokumen']), 'draft' => true, 'action' => null, 'method' => 'POST', 'submit' => 'Simpan'])
            </x-modal>
        </section>
        @endif

        {{-- Jadwal pengisian nilai dosen: CRUD bentuk penilaian dosen + jadwal di periode ini (modal tambah/ubah di atas halaman). --}}
        <section class="panel">
            <div class="panel-head">
                <h2 class="panel-title">Jadwal pengisian nilai dosen</h2>
            </div>
            @if ($editing)
                @error('component')<p class="mx-4 mb-4 rounded-lg border border-bad-line bg-bad-tint px-4 py-3 text-sm text-bad-ink sm:mx-6">{{ $message }}</p>@enderror
                <x-scroll-pager>
                    <table class="data-table">
                        <thead><tr><th>Bentuk penilaian</th><th>Penilai</th><th>Jenis dokumen</th><th>Dibuka</th><th>Deadline</th><th><span class="sr-only">Aksi</span></th></tr></thead>
                        <tbody>
                            @foreach ($components as $c)
                                @php $w = $period->assessmentWindows?->firstWhere('component', $c->key); @endphp
                                <tr x-data="registrationForm" data-open="{{ old('_component') === (string) $c->id && $errors->any() ? 'true' : 'false' }}">
                                    <td class="font-medium">{{ $c->label }}</td>
                                    <td class="text-muted">{{ $c->rolesLabel() }}</td>
                                    <td class="text-muted">{{ $docTypeLabels($c->document_types) }}</td>
                                    <td class="tabular {{ $w ? 'text-ink' : 'text-muted' }}">{{ $w ? $w->opens_at->translatedFormat('j M Y, H:i') : 'Belum diatur' }}</td>
                                    <td class="tabular {{ $w ? 'text-ink' : 'text-muted' }}">{{ $w ? $w->due_at->translatedFormat('j M Y, H:i') : 'Belum diatur' }}</td>
                                    <td>
                                        <div class="flex justify-end gap-1">
                                            <button type="button" class="btn btn-secondary btn-sm" @click="toggle" :aria-expanded="expanded">Ubah</button>
                                            <form method="POST" action="{{ route('admin.periods.components.destroy', [$period, $c]) }}" data-confirm="Hapus bentuk penilaian “{{ $c->label }}”? Jadwal di semua periode dan bobotnya di CLO & indikator ikut terhapus.">@csrf @method('DELETE')
                                                <button class="btn btn-ghost btn-sm text-bad-ink" aria-label="Hapus {{ $c->label }}">Hapus</button>
                                            </form>
                                        </div>
                                        <x-modal width="sm:max-w-xl">
                                            <x-slot:heading>Ubah bentuk penilaian · {{ $c->label }}</x-slot:heading>
                                            @include('admin.periods._component-fields', ['c' => $c, 'w' => $w, 'docTypes' => $docTypes, 'action' => route('admin.periods.components.update', [$period, $c]), 'method' => 'PUT', 'submit' => 'Simpan'])
                                        </x-modal>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-scroll-pager>
                {{-- Tambah bentuk penilaian lewat modal di atas halaman; terbuka lagi bila simpan gagal validasi. --}}
                <div x-data="registrationForm" data-open="{{ old('_component') === 'baru' && $errors->any() ? 'true' : 'false' }}" class="border-t border-rule px-4 py-4 sm:px-6">
                    <button type="button" class="btn btn-secondary" @click="open" :aria-expanded="expanded">
                        <x-heroicon-o-plus class="h-5 w-5" /> Tambah Jadwal Penilaian
                    </button>
                    <x-modal width="sm:max-w-xl">
                        <x-slot:heading>Tambah jadwal penilaian</x-slot:heading>
                        @include('admin.periods._component-fields', ['c' => new \App\Models\AssessmentComponent(['roles' => [], 'document_types' => []]), 'w' => null, 'docTypes' => $docTypes, 'action' => route('admin.periods.components.store', $period), 'method' => 'POST', 'submit' => 'Tambah'])
                    </x-modal>
                </div>
            @else
                {{-- Periode baru: bentuk penilaian mengikuti "Salin data master dari" (tabel per sumber, Alpine "periodDraft"
                     pickSource); jadwal diisi langsung, bentuk penilaian baru disusun di browser (Alpine "draftComponents"). --}}
                <div x-data="draftComponents" data-old="{{ old('draft_components', '[]') }}" data-roles="{{ json_encode(\App\Models\AssessmentComponent::LECTURER_ROLES) }}" data-documents="{{ json_encode(config('capstone.document_types')) }}">
                    <input type="hidden" form="period-form" name="draft_components" value="{{ old('draft_components', '[]') }}">
                    @php $windowErrors = collect($errors->getMessages())->filter(fn ($m, $k) => str_starts_with($k, 'windows.') || $k === 'draft_components')->flatten()->unique(); @endphp
                    @if ($windowErrors->isNotEmpty())
                        <ul class="{{ $draftError }} mx-4 mt-4 list-inside list-disc sm:mx-6">@foreach ($windowErrors as $message)<li>{{ $message }}</li>@endforeach</ul>
                    @endif
                    <x-scroll-pager>
                        <table class="data-table">
                            <thead><tr><th>Bentuk penilaian</th><th>Penilai</th><th>Jenis dokumen</th><th>Dibuka</th><th>Deadline</th><th><span class="sr-only">Aksi</span></th></tr></thead>
                            @foreach ($componentSets ?? [] as $sourceId => $set)
                                <tbody data-source="{{ $sourceId }}">
                                    @foreach ($set as $c)
                                        <tr>
                                            <td class="font-medium">{{ $c->label }}</td>
                                            <td class="text-muted">{{ $c->rolesLabel() }}</td>
                                            <td class="text-muted">{{ $docTypeLabels($c->document_types) }}</td>
                                            <td><label class="sr-only" for="w-{{ $sourceId }}-{{ $c->key }}-opens">Dibuka {{ $c->label }}</label>
                                                <input id="w-{{ $sourceId }}-{{ $c->key }}-opens" type="datetime-local" form="period-form" name="windows[{{ $c->key }}][opens_at]" class="input min-w-[13rem]" value="{{ old("windows.{$c->key}.opens_at") }}"></td>
                                            <td><label class="sr-only" for="w-{{ $sourceId }}-{{ $c->key }}-due">Deadline {{ $c->label }}</label>
                                                <input id="w-{{ $sourceId }}-{{ $c->key }}-due" type="datetime-local" form="period-form" name="windows[{{ $c->key }}][due_at]" class="input min-w-[13rem]" value="{{ old("windows.{$c->key}.due_at") }}"></td>
                                            <td></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            @endforeach
                            <tbody data-draft-components @click="listClick"></tbody>
                        </table>
                    </x-scroll-pager>
                    <div class="border-t border-rule px-4 py-4 sm:px-6">
                        <button type="button" class="btn btn-secondary" @click="open"><x-heroicon-o-plus class="h-5 w-5" /> Tambah Jadwal Penilaian</button>
                        <p class="mt-2 text-caption text-muted">Kosongkan jadwal bila belum ditentukan; dapat diatur nanti.</p>
                    </div>
                    <x-modal width="sm:max-w-xl">
                        <x-slot:heading>Tambah jadwal penilaian</x-slot:heading>
                        <form data-draft-component @submit.prevent="save" class="space-y-4">
                            <div>
                                <label class="field-label" for="draft-komp-label">Bentuk penilaian<span class="text-bad-ink" aria-hidden="true"> *</span></label>
                                <input id="draft-komp-label" name="label" class="input" placeholder="Seminar Kemajuan" required maxlength="100">
                            </div>
                            <fieldset>
                                <legend class="field-label">Penilai<span class="text-bad-ink" aria-hidden="true"> *</span></legend>
                                <div class="flex flex-wrap gap-x-6 gap-y-1">
                                    @foreach (\App\Models\AssessmentComponent::LECTURER_ROLES as $value => $text)
                                        <label class="flex min-h-[44px] items-center gap-3 text-sm text-ink"><input type="checkbox" class="checkbox" name="roles[]" value="{{ $value }}"> {{ $text }}</label>
                                    @endforeach
                                </div>
                                <p class="field-hint">Centang keduanya bila pembimbing dan penguji sama-sama menilai.</p>
                            </fieldset>
                            <fieldset>
                                <legend class="field-label">Jenis dokumen</legend>
                                {{-- Diisi dari milestone berjenis Dokumen yang sudah disusun di atas (draftComponents.open) --}}
                                <div data-draft-doc-types class="flex flex-wrap gap-x-6 gap-y-1"></div>
                                <p data-draft-doc-empty class="text-sm text-muted">Belum ada milestone berjenis Dokumen. Tambahkan milestone dokumen terlebih dahulu.</p>
                            </fieldset>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div><label class="field-label" for="draft-komp-opens">Dibuka</label><input id="draft-komp-opens" type="datetime-local" name="opens_at" class="input"></div>
                                <div><label class="field-label" for="draft-komp-due">Deadline</label><input id="draft-komp-due" type="datetime-local" name="due_at" class="input"></div>
                            </div>
                            <p class="text-caption text-muted">Kosongkan jadwal bila belum ditentukan; dapat diatur nanti lewat tombol Ubah.</p>
                            <div class="flex justify-end gap-3 border-t border-rule pt-4">
                                <button type="button" class="btn btn-ghost" @click="close">Batal</button>
                                <button class="btn btn-primary">Tambah</button>
                            </div>
                        </form>
                    </x-modal>
                </div>
            @endif
        </section>

        {{-- Aturan tambahan periode: syarat log bimbingan (dipakai berdasarkan jenis dokumen milestone). --}}
        <section class="panel">
            <div class="panel-head">
                <h2 class="panel-title">Aturan tambahan</h2>
            </div>
            <div class="panel-body">
                <x-field label="Minimal log bimbingan disetujui" name="min_approved_logbooks" required
                         hint="Syarat sebelum tim dapat mengumpulkan Laporan Akhir dan Materi Presentasi. Isi 0 bila tanpa syarat.">
                    <input id="min_approved_logbooks" form="period-form" type="number" min="0" max="50" name="min_approved_logbooks" class="input tabular sm:max-w-[10rem]"
                           value="{{ old('min_approved_logbooks', $period->min_approved_logbooks ?? config('capstone.min_approved_logbooks')) }}" required>
                </x-field>
            </div>
        </section>

        {{-- F35 — satu folder Google Drive per milestone dokumen; isian bertambah/berkurang mengikuti milestone berjenis Dokumen. --}}
        <section class="panel">
            <div class="panel-head">
                <h2 class="panel-title">Folder Google Drive Database</h2>
            </div>
            {{-- Tombol "Tambah Folder" (kiri bawah) → modal: pilih milestone dokumen + tautan folder → Simpan. Alpine
                 "driveFolders"; pada periode baru baris folder mengikuti milestone dokumen draf (periodDraft). --}}
            <div x-data="driveFolders" @drive-rows-changed.window="refresh">
                @php $documentMilestones = $editing ? $period->milestones->where('kind', 'dokumen')->values() : collect(); @endphp
                @if (! $editing)
                    @php $folderErrors = collect($errors->getMessages())->filter(fn ($m, $k) => str_starts_with($k, 'drive_folders.'))->flatten()->unique(); @endphp
                    @if ($folderErrors->isNotEmpty())<p class="{{ $draftError }} mx-4 mt-4 sm:mx-6">{{ $folderErrors->first() }}</p>@endif
                @endif
                <div data-folder-rows x-show="hasFolders" class="panel-body grid gap-4 sm:grid-cols-2">
                    @foreach ($documentMilestones as $m)
                        @php $value = old('drive_folders.'.$m->id, $m->driveFolderUrl()); @endphp
                        @include('admin.periods._drive-folder-row', ['key' => $m->id, 'label' => $m->name, 'value' => $value,
                            'hidden' => blank($value) && ! $errors->has('drive_folders.'.$m->id)])
                    @endforeach
                </div>
                <p x-show="noMilestones" x-cloak class="panel-body text-sm text-muted">Belum ada milestone berjenis Dokumen. Tambahkan milestone dokumen agar foldernya dapat dipilih di sini.</p>
                <p x-show="noFolders" x-cloak class="panel-body text-sm text-muted">Belum ada folder. Klik Tambah Folder untuk memilih milestone dokumen.</p>
                <div class="border-t border-rule px-4 py-4 sm:px-6">
                    <button type="button" class="btn btn-secondary disabled:cursor-not-allowed disabled:opacity-50" @click="open" :disabled="cannotAdd" :title="addTitle"><x-heroicon-o-plus class="h-5 w-5" /> Tambah Folder</button>
                </div>
                <x-modal width="sm:max-w-xl">
                    <x-slot:heading>Tambah folder Google Drive</x-slot:heading>
                    <form @submit.prevent="save" class="space-y-4">
                        <div>
                            <label class="field-label" for="drive-pick">Milestone dokumen<span class="text-bad-ink" aria-hidden="true"> *</span></label>
                            <select id="drive-pick" x-ref="pick" class="input" required></select>
                            <p class="field-hint">Dokumen tim pada milestone ini diunggah ke folder tersebut.</p>
                        </div>
                        <div>
                            <label class="field-label" for="drive-url">Tautan folder<span class="text-bad-ink" aria-hidden="true"> *</span></label>
                            <input id="drive-url" x-ref="url" class="input" inputmode="url" required placeholder="https://drive.google.com/drive/folders/…">
                            <p class="field-hint">Salin tautan folder dari address bar Google Drive.</p>
                        </div>
                        <div class="flex justify-end gap-3 border-t border-rule pt-4">
                            <button type="button" class="btn btn-ghost" @click="close">Batal</button>
                            <button class="btn btn-primary">Simpan</button>
                        </div>
                    </form>
                </x-modal>
                @unless ($editing)
                    <template data-folder-template>@include('admin.periods._drive-folder-row', ['key' => 'baru', 'label' => '', 'value' => '', 'hidden' => true])</template>
                @endunless
            </div>
        </section>

        {{-- Akun admin prodi: satu akun per periode, dapat masuk di beberapa perangkat sekaligus. Email yang sama di
             beberapa periode = satu akun yang dapat berganti periode lewat menu periode di sidebar. --}}
        @php
            $admin = $editing ? $period->admin : null;
            $shared = $sharedPeriods ?? collect();
        @endphp
        <section id="akun-admin-prodi" class="panel scroll-mt-20">
            <div class="panel-head">
                <h2 class="panel-title">Akun admin prodi</h2>
            </div>
            <div class="panel-body grid gap-4 sm:grid-cols-2">
                <x-field label="Email" name="admin_email"
                         hint="Pakai email yang sama dengan periode lain agar satu akun dapat berganti di antara periode-periode tersebut.">
                    <input id="admin_email" form="period-form" type="email" name="admin_email" class="input" autocomplete="off"
                           value="{{ old('admin_email', $admin?->email) }}" placeholder="admin.prodi@gmail.com" maxlength="150">
                </x-field>
                <x-field label="Password" name="admin_password"
                         :hint="$admin ? 'Kosongkan bila tidak diganti. Mengganti password mengeluarkan akun ini dari semua perangkat.' : 'Minimal 8 karakter. Tidak perlu diisi bila email sudah dipakai periode lain.'">
                    <x-password-input id="admin_password" form="period-form" name="admin_password" autocomplete="new-password" minlength="8" maxlength="100" />
                </x-field>
                @if ($shared->isNotEmpty())
                    <div class="rounded-lg border border-rule bg-sunken px-4 py-3 text-sm sm:col-span-2">
                        <p class="font-medium">Akun ini juga dipakai {{ $shared->count() }} periode lain</p>
                        <p class="mt-0.5 text-muted">Password berlaku untuk semua periode berikut, dan admin prodi dapat berganti ke periode ini lewat menu periode:</p>
                        <ul class="mt-2 space-y-1">
                            @foreach ($shared as $other)
                                <li><a href="{{ route('admin.periods.edit', $other) }}" class="link">{{ $other->name }}</a> <span class="text-muted">· {{ $other->prodi_name }} · {{ $other->label() }}</span></li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </section>

    </div>

    {{-- Tombol simpan utama: paling bawah, selebar konten --}}
    <div class="mt-8">
        <button form="period-form" class="btn btn-primary min-h-[56px] w-full text-base" data-once>
            <x-heroicon-o-check class="h-5 w-5" /> {{ $editing ? 'Simpan Periode' : 'Buat Periode' }}
        </button>
    </div>
</x-layouts.app>
