@php
    $editing = $period->exists;
    $dt = fn ($v) => $v ? $v->format('Y-m-d\TH:i') : '';
    $components = collect(config('capstone.components'))->except('peer_review');
@endphp
<x-layouts.app :title="$editing ? $period->name : 'Periode baru'" section="Admin · Periode">
    <x-page-head :title="$editing ? $period->name : 'Periode baru'" lede="Atur rentang periode, jendela penilaian, folder Google Drive per prodi, serta milestone dan tenggat setiap tahapan.">
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

    <div class="space-y-6">
        <section class="panel">
            <div class="panel-head"><h2 class="panel-title">Identitas periode</h2></div>
            <div class="panel-body grid gap-4 sm:grid-cols-2">
                <x-field label="Nama periode" name="name" required class="sm:col-span-2">
                    <input id="name" form="period-form" name="name" class="input" value="{{ old('name', $period->name) }}" placeholder="Capstone Ganjil 2026/2027" required>
                </x-field>
                <x-field label="Tahun akademik" name="academic_year" required hint="Format 2026/2027">
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
                    <input type="date" id="starts_on" form="period-form" name="starts_on" class="input" value="{{ old('starts_on', $period->starts_on?->format('Y-m-d')) }}" required>
                </x-field>
                <x-field label="Tanggal selesai (minggu ke-16)" name="ends_on" required>
                    <input type="date" id="ends_on" form="period-form" name="ends_on" class="input" value="{{ old('ends_on', $period->ends_on?->format('Y-m-d')) }}" required>
                </x-field>
            </div>
        </section>


        {{-- F5 — aturan registrasi tim untuk seluruh periode (bukan per prodi) --}}
        @php
            $courseLines = old('rule.courses', $rule?->courses->map(fn ($c) => $c->code.' - '.$c->name)->implode("
"));
            $specLines = old('rule.specializations', $rule?->specializations->pluck('name')->implode("
"));
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
                <x-field label="Peminatan" name="rule.specializations" class="sm:col-span-3 lg:col-span-1" hint="Satu per baris. Dipilih dosen sebagai kompetensi yang diharapkan pada topik.">
                    <textarea form="period-form" id="rule.specializations" name="rule[specializations]" rows="4" class="input text-sm" placeholder="Enterprise Systems">{{ $specLines }}</textarea>
                </x-field>
            </div>
        </section>

        @if ($editing)
        <section class="panel">
            <div class="panel-head">
                <h2 class="panel-title">Milestone</h2>
                <span class="text-sm text-muted">{{ $period->milestones->count() }} milestone</span>
            </div>
            @if ($period->milestones->isNotEmpty())
                <div class="panel-body border-b border-rule"><x-week-ruler :period="$period" /></div>
                <ul class="divide-y divide-rule">
                    @foreach ($period->milestones as $m)
                        <li x-data="disclosure" class="px-4 py-3 sm:px-6">
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                                <span class="w-14 shrink-0 tabular text-sm text-muted">{{ $m->week_no ? 'Mg '.$m->week_no : '—' }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="font-medium">{{ $m->name }}</p>
                                    <p class="text-sm text-muted"><span class="mr-1 rounded-md border border-rule bg-sunken px-1.5 py-0.5 text-caption font-medium">{{ config('capstone.milestone_categories.'.$m->category()) }}</span> @if ($m->document_type){{ config('capstone.document_types.'.$m->document_type) }} ({{ $m->fileTypesLabel() }})@else{{ $m->kindLabel() }}@endif · {{ $m->starts_at->translatedFormat('j M H:i') }} → {{ $m->due_at->translatedFormat('j M Y H:i') }}</p>
                                </div>
                                <button type="button" class="btn btn-ghost btn-sm" @click="toggle" :aria-expanded="expanded">Ubah</button>
                                <form method="POST" action="{{ route('admin.milestones.destroy', $m) }}" data-confirm="Hapus milestone “{{ $m->name }}”?">@csrf @method('DELETE')
                                    <button class="btn btn-ghost btn-sm text-bad-ink" aria-label="Hapus {{ $m->name }}">Hapus</button>
                                </form>
                            </div>
                            <x-modal width="sm:max-w-3xl">
                                <x-slot:heading>Ubah milestone · {{ $m->name }}</x-slot:heading>
                                @include('admin.periods._milestone-fields', ['m' => $m, 'action' => route('admin.milestones.update', $m), 'method' => 'PUT', 'submit' => 'Simpan milestone'])
                            </x-modal>
                        </li>
                    @endforeach
                </ul>
            @endif
            <div class="border-t border-rule bg-sunken px-4 py-4 sm:px-6">
                <h3 class="mb-3 text-sm font-semibold">Tambah milestone</h3>
                @include('admin.periods._milestone-fields', ['m' => new \App\Models\Milestone(['kind' => 'dokumen']), 'action' => route('admin.milestones.store', $period), 'method' => 'POST', 'submit' => 'Tambah'])
            </div>
        </section>
        @else
        <section class="panel"><div class="panel-head"><h2 class="panel-title">Milestone</h2></div><p class="panel-body text-sm text-muted">Simpan periode terlebih dahulu untuk menambahkan milestone.</p></section>
        @endif

        <section class="panel">
            <div class="panel-head">
                <h2 class="panel-title">Jadwal pengisian nilai dosen</h2>
                <p class="text-sm text-muted">Kapan dosen dapat mengisi nilai tiap bentuk penilaian; dipakai pengingat dosen dan QR penilaian.</p>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Bentuk penilaian</th><th>Penilai</th><th>Dibuka</th><th>Tenggat</th></tr></thead>
                    <tbody>
                        @foreach ($components as $key => $meta)
                            @php $w = $period->assessmentWindows?->firstWhere('component', $key); @endphp
                            <tr>
                                <td class="font-medium">{{ $meta['label'] }}</td>
                                <td class="capitalize text-muted">{{ $meta['role'] }}</td>
                                <td><label class="sr-only" for="w-{{ $key }}-o">Dibuka {{ $meta['label'] }}</label><input id="w-{{ $key }}-o" type="datetime-local" form="period-form" name="windows[{{ $key }}][opens_at]" class="input min-w-[13rem]" value="{{ old("windows.$key.opens_at", $dt($w?->opens_at)) }}"></td>
                                <td><label class="sr-only" for="w-{{ $key }}-d">Tenggat {{ $meta['label'] }}</label><input id="w-{{ $key }}-d" type="datetime-local" form="period-form" name="windows[{{ $key }}][due_at]" class="input min-w-[13rem]" value="{{ old("windows.$key.due_at", $dt($w?->due_at)) }}"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel">
            <div class="panel-head">
                <h2 class="panel-title">Folder Google Drive</h2>
                <p class="text-sm text-muted">Isi ID folder (bagian akhir URL folder). Folder per mahasiswa dibuat otomatis di dalamnya.</p>
            </div>
            <div class="panel-body grid gap-4 sm:grid-cols-2">
                <x-field label="Folder induk (cadangan)" name="drive_root_folder_id" class="sm:col-span-2" hint="Dipakai bila prodi belum memiliki folder sendiri.">
                    <input id="drive_root_folder_id" form="period-form" name="drive_root_folder_id" class="input tabular" value="{{ old('drive_root_folder_id', $period->drive_root_folder_id) }}">
                </x-field>
                @foreach ($prodis as $prodi)
                    <x-field :label="'Prodi '.$prodi->code.' — '.$prodi->name" :name="'folders.'.$prodi->code">
                        <input form="period-form" id="folders.{{ $prodi->code }}" name="folders[{{ $prodi->code }}]" class="input tabular" value="{{ old("folders.{$prodi->code}", $period->driveFolders?->firstWhere('prodi_code', $prodi->code)?->folder_id) }}">
                    </x-field>
                @endforeach
            </div>
        </section>

    </div>

    @if ($editing)
        @if ($period->teams()->doesntExist())
            <form method="POST" action="{{ route('admin.periods.destroy', $period) }}" data-confirm="Hapus periode {{ $period->name }}? Seluruh milestone di dalamnya ikut terhapus." data-confirm-label="Hapus periode" class="mt-6 flex justify-end">
                @csrf @method('DELETE')
                <button class="btn btn-ghost text-bad-ink">Hapus periode ini</button>
            </form>
        @endif
    @endif

    {{-- Tombol simpan utama: paling bawah, selebar konten --}}
    <div class="mt-8">
        <button form="period-form" class="btn btn-primary min-h-[56px] w-full text-base" data-once>
            <x-heroicon-o-check class="h-5 w-5" /> {{ $editing ? 'Simpan Periode' : 'Buat Periode' }}
        </button>
    </div>
</x-layouts.app>
