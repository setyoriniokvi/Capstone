{{-- Formulir modal admin untuk menambah / mengubah topik di bank topik dosen (Alpine "topicForm" pada pembungkus; tombol
     "Tambah Topik" mengosongkan formulir, tombol "Edit" mengisinya dari data-topic). Isian sama dengan formulir dosen,
     ditambah dosen pengusul utama (Alpine "lecturerPicker") dan pengusul bersama (Alpine "proposerPicker"); keduanya dicari
     menurut nama/NIP/peminatan, dan dosen pengusul utama tidak muncul di pilihan pengusul bersama.
     Topik dari admin langsung terbit tanpa approval. Butuh $programs, $prodiSpecializations, $lecturers. --}}
@php
    $pickerOptions = $lecturers->map(fn ($l) => [
        'id' => $l->id, 'name' => $l->name, 'nip' => (string) $l->lecturerProfile?->nip,
        'code' => (string) $l->lecturerProfile?->lecturer_code, 'specialization' => (string) $l->lecturerProfile?->specialization,
    ])->values();
    $selectedProdi = old('prodi_codes', []);
    $selectedSpecializations = old('specializations', []);
@endphp
<script type="application/json" id="data-dosen">{!! $pickerOptions->toJson(JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
<x-modal>
    <x-slot:heading><span x-text="heading">Tambah topik</span></x-slot:heading>
    <form x-ref="panel" method="POST" :action="action" action="{{ route('admin.lecturer-topics.store') }}">
        @csrf
        <input type="hidden" name="_method" :value="method" value="POST">
        <input type="hidden" name="_form_action" :value="action">
        <input type="hidden" name="_form_editing" :value="editingFlag">
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field label="Topik" name="title" required class="sm:col-span-2">
                <input id="title" name="title" class="input" value="{{ old('title') }}" required maxlength="255">
            </x-field>
            <x-field label="Deskripsi" name="description" required class="sm:col-span-2" hint="Topik wajib mencakup permasalahan pada sistem terintegrasi.">
                <textarea id="description" name="description" rows="5" class="input" required>{{ old('description') }}</textarea>
            </x-field>
            <x-field label="Kompetensi yang diharapkan" name="competencies" required class="sm:col-span-2">
                <textarea id="competencies" name="competencies" rows="3" class="input" required maxlength="5000">{{ old('competencies') }}</textarea>
            </x-field>

            {{-- Program studi & peminatan: kartu centang sejajar; boleh memilih lebih dari satu --}}
            @foreach ([
                ['name' => 'prodi_codes', 'label' => 'Program studi', 'options' => $programs->pluck('name', 'code'), 'selected' => $selectedProdi],
                ['name' => 'specializations', 'label' => 'Peminatan', 'options' => $prodiSpecializations->combine($prodiSpecializations), 'selected' => $selectedSpecializations],
            ] as $group)
                <fieldset class="flex min-w-0 flex-col">
                    <legend class="field-label">{{ $group['label'] }}<span class="text-bad-ink" aria-hidden="true"> *</span></legend>
                    <div class="grid flex-1 content-start gap-1 rounded-lg border border-rule px-3 py-2">
                        @forelse ($group['options'] as $value => $text)
                            <label class="flex min-h-[40px] items-center gap-3 text-sm text-ink">
                                <input type="checkbox" class="checkbox" name="{{ $group['name'] }}[]" value="{{ $value }}" @checked(in_array($value, $group['selected'], true))>
                                {{ $text }}
                            </label>
                        @empty
                            <p class="py-2 text-sm text-muted">Belum ada pilihan pada periode ini.</p>
                        @endforelse
                    </div>
                    @error($group['name'])<p class="field-error">{{ $message }}</p>@enderror
                    @error($group['name'].'.*')<p class="field-error">{{ $message }}</p>@enderror
                </fieldset>
            @endforeach

            <x-field label="Instansi / organisasi / perusahaan objek" name="object" required>
                <input id="object" name="object" class="input" value="{{ old('object') }}" required maxlength="255">
            </x-field>
            <x-field label="Jumlah tim yang tersedia" name="quota" required hint="Kuota tim yang dapat diterima pada topik ini.">
                <input id="quota" type="number" min="1" max="20" name="quota" class="input tabular" value="{{ old('quota', 1) }}" required>
            </x-field>

            {{-- Pengusul utama: satu dosen, dicari menurut nama / NIP / peminatan (Alpine "lecturerPicker") --}}
            <div class="relative sm:col-span-2" x-data="lecturerPicker" data-selected="{{ old('lecturer_id') }}" @primary-set.window="load" @click.outside="hide" @keydown.escape="hide">
                <label class="field-label" for="primary-search">Dosen pengusul utama<span class="text-bad-ink" aria-hidden="true"> *</span></label>
                <input type="hidden" name="lecturer_id" :value="selectedId">
                <div x-show="hasSelection" class="input flex items-center justify-between gap-3">
                    <span class="min-w-0 truncate" x-text="selectedLabel"></span>
                    <button type="button" class="link shrink-0 text-sm" @click="clear">Ganti</button>
                </div>
                <div x-show="noSelection" class="relative">
                    <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-faint" />
                    <input id="primary-search" x-ref="search" type="text" class="input pl-9" placeholder="Cari nama, NIP, atau peminatan dosen" autocomplete="off"
                           role="combobox" aria-autocomplete="list" aria-controls="primary-list" :aria-expanded="expanded"
                           :value="query" @input="search" @focus="show">
                </div>
                <ul id="primary-list" x-show="open" role="listbox"
                    class="absolute inset-x-0 top-full z-30 mt-1 max-h-72 overflow-y-auto rounded-lg border border-rule bg-surface py-1 shadow-overlay">
                    <template x-for="option in results" :key="option.id">
                        <li role="option">
                            <button type="button" class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-sunken focus-visible:bg-sunken" :data-id="option.id" @click="choose">
                                <span class="min-w-0">
                                    <span class="block truncate text-ink" x-text="option.name"></span>
                                    <span class="block truncate text-caption text-muted" x-text="option.specialization"></span>
                                </span>
                                <span class="shrink-0 tabular text-caption text-muted" x-text="option.nip"></span>
                            </button>
                        </li>
                    </template>
                    <li x-show="noResults" class="px-3 py-2 text-sm text-muted">Dosen tidak ditemukan.</li>
                </ul>
                @error('lecturer_id')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            {{-- Pengusul bersama (opsional) --}}
            <div class="relative sm:col-span-2" x-data="proposerPicker" data-selected="{{ implode(',', old('proposer_ids', [])) }}" data-primary-id="{{ old('lecturer_id') }}" @proposers-set.window="load" @primary-chosen.window="exclude" @click.outside="hide" @keydown.escape="hide">
                <label class="field-label" for="proposer-search">Pengusul bersama <span class="font-normal text-muted">(opsional)</span></label>
                <ul class="mb-2 flex flex-wrap gap-2" aria-label="Pengusul bersama terpilih">
                    <template x-for="person in chosen" :key="person.id">
                        <li class="inline-flex min-h-[36px] items-center gap-1 rounded-full border border-rule bg-sunken pl-3 pr-1 text-sm text-ink">
                            <span x-text="person.name"></span>
                            <input type="hidden" name="proposer_ids[]" :value="person.id">
                            <button type="button" class="grid h-7 w-7 place-items-center rounded-full text-muted hover:bg-surface hover:text-bad-ink" :data-id="person.id" @click="remove" :aria-label="person.removeLabel">
                                <x-heroicon-o-x-mark class="h-4 w-4" />
                            </button>
                        </li>
                    </template>
                </ul>
                <div class="relative">
                    <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-faint" />
                    <input id="proposer-search" x-ref="search" type="text" class="input pl-9" placeholder="Cari nama, NIP, atau peminatan dosen" autocomplete="off"
                           role="combobox" aria-autocomplete="list" aria-controls="proposer-list" :aria-expanded="expanded"
                           :value="query" @input="search" @focus="show">
                </div>
                <ul id="proposer-list" x-show="open" role="listbox"
                    class="absolute inset-x-0 top-full z-20 mt-1 max-h-72 overflow-y-auto rounded-lg border border-rule bg-surface py-1 shadow-overlay">
                    <template x-for="option in results" :key="option.id">
                        <li role="option">
                            <button type="button" class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-sunken focus-visible:bg-sunken" :data-id="option.id" @click="choose">
                                <span class="min-w-0">
                                    <span class="block truncate text-ink" x-text="option.name"></span>
                                    <span class="block truncate text-caption text-muted" x-text="option.specialization"></span>
                                </span>
                                <span class="shrink-0 tabular text-caption text-muted" x-text="option.nip"></span>
                            </button>
                        </li>
                    </template>
                    <li x-show="noResults" class="px-3 py-2 text-sm text-muted">Dosen tidak ditemukan atau sudah dipilih.</li>
                </ul>
                @error('proposer_ids.*')<p class="field-error">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="mt-5 flex flex-wrap justify-end gap-3 border-t border-rule pt-4">
            <button type="button" class="btn btn-ghost" @click="close">Batal</button>
            <button class="btn btn-primary" data-once x-text="submitLabel">Tambah ke bank topik</button>
        </div>
        <p class="mt-3 text-caption text-muted">Topik yang ditambahkan atau diubah admin langsung terbit di bank topik tanpa approval; seluruh dosen pengusul diberi notifikasi saat topik ditambahkan.</p>
    </form>
</x-modal>
