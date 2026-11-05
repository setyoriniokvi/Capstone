{{-- F17 — File pendukung admin: kartu daftar file yang sudah diunggah (judul, kategori · nama file · ukuran, deskripsi,
     role yang dapat melihat) dengan tombol Unduh · Lihat · Hapus · Ubah (modal; file baru opsional). Kanan atas: "Tambah File" membuka formulir di modal
     (Judul, Kategori, Deskripsi, Role akses melihat — centang Dosen / Mahasiswa, File); terbuka lagi bila simpan gagal. --}}
@php
    $editingId = old('_edit');
    $adding = $errors->any() && ! $editingId;
    $oldRoles = $adding ? old('visible_to', []) : array_keys($roles);
    $roleChip = 'rounded-md border border-rule bg-sunken px-2 py-0.5 text-caption font-medium text-muted';
@endphp
<x-layouts.app title="File Pendukung">
    <x-page-head title="File pendukung" lede="Panduan Capstone, slide sosialisasi, dan materi lain. Atur role yang dapat melihat setiap file.">
        <x-slot:actions>
            <div x-data="registrationForm" data-open="{{ $adding ? 'true' : 'false' }}">
                <button type="button" class="btn btn-primary" @click="open" :aria-expanded="expanded"><x-heroicon-o-plus class="h-5 w-5" /> Tambah File</button>
                <x-modal width="sm:max-w-xl">
                    <x-slot:heading>Tambah file pendukung</x-slot:heading>
                    <form method="POST" action="{{ route('admin.files.store') }}" enctype="multipart/form-data" class="space-y-4">
                        @csrf
                        <x-field label="Judul" name="title" required><input id="title" name="title" class="input" maxlength="150" value="{{ old('title') }}" required></x-field>
                        <x-field label="Kategori" name="category" required hint="Contoh: Panduan, Slide Sosialisasi, Template">
                            <input id="category" name="category" class="input" maxlength="60" list="categories" value="{{ old('category') }}" required>
                            <datalist id="categories">@foreach ($categories as $c)<option value="{{ $c }}">@endforeach</datalist>
                        </x-field>
                        <x-field label="Deskripsi" name="description"><textarea id="description" name="description" rows="3" class="input" maxlength="1000">{{ old('description') }}</textarea></x-field>
                        <fieldset>
                            <legend class="field-label">Role akses melihat<span class="text-bad-ink" aria-hidden="true"> *</span></legend>
                            <div class="flex flex-wrap gap-x-6 gap-y-2">
                                @foreach ($roles as $value => $label)
                                    <label class="flex min-h-[40px] cursor-pointer items-center gap-2 text-sm text-ink">
                                        <input type="checkbox" class="checkbox" name="visible_to[]" value="{{ $value }}" @checked(in_array($value, $oldRoles, true))>
                                        {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                            <p class="field-hint">Admin selalu dapat melihat semua file.</p>
                            @error('visible_to')<p class="field-error">{{ $message }}</p>@enderror
                            @error('visible_to.*')<p class="field-error">{{ $message }}</p>@enderror
                        </fieldset>
                        <x-field label="File" name="file" required hint="PDF, DOCX, PPTX, XLSX, gambar, atau ZIP. Maks. 50 MB."><input id="file" type="file" name="file" class="input py-2" required></x-field>
                        <div class="flex justify-end gap-3 border-t border-rule pt-4">
                            <button type="button" class="btn btn-ghost" @click="close">Batal</button>
                            <button class="btn btn-primary" data-once>Unggah</button>
                        </div>
                    </form>
                </x-modal>
            </div>
        </x-slot:actions>
    </x-page-head>

    <section class="panel overflow-hidden" aria-labelledby="file-judul">
        <div class="flex items-center justify-between gap-3 border-b border-rule px-5 py-4 sm:px-6">
            <h2 id="file-judul" class="text-lg font-semibold text-ink">File yang sudah diunggah</h2>
            <span class="text-sm text-muted"><span class="tabular font-medium text-ink">{{ $files->count() }}</span> file</span>
        </div>
        @if ($files->isEmpty())
            <x-empty title="Belum ada file pendukung" icon="folder">Unggah lewat tombol Tambah File.</x-empty>
        @else
            <ul class="divide-y divide-rule">
                @foreach ($files as $file)
                    @php
                        $editing = $errors->any() && (string) $editingId === (string) $file->id;
                        $fileRoles = $editing ? old('visible_to', []) : ($file->visible_to ?? []);
                    @endphp
                    <li x-data="registrationForm" data-open="{{ $editing ? 'true' : 'false' }}" class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:px-6">
                        <div class="flex min-w-0 flex-1 gap-3">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-sunken text-muted" aria-hidden="true"><x-heroicon-o-document class="h-5 w-5" /></span>
                            <div class="min-w-0">
                                <p class="font-medium text-ink">{{ $file->title }}</p>
                                <p class="text-caption text-muted">{{ $file->category }} · {{ $file->original_name }} · {{ \App\Support\Format::bytes($file->size) }}</p>
                                @if ($file->description)<p class="mt-1 text-sm text-muted">{{ $file->description }}</p>@endif
                                <p class="mt-2 flex flex-wrap items-center gap-1.5 text-caption text-muted">
                                    Dapat dilihat:
                                    @forelse ($file->visible_to ?? [] as $role)<span class="{{ $roleChip }}">{{ $roles[$role] ?? ucfirst($role) }}</span>@empty<span>hanya admin</span>@endforelse
                                </p>
                            </div>
                        </div>
                        <div class="flex shrink-0 flex-wrap gap-2 sm:justify-end">
                            <a download href="{{ route('files.download', $file) }}" class="btn btn-secondary btn-sm"><x-heroicon-o-arrow-down-tray class="h-4 w-4" />Unduh<span class="sr-only"> {{ $file->title }}</span></a>
                            @if ($file->isPreviewable())
                                <a href="{{ route('files.show', $file) }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm"><x-heroicon-o-eye class="h-4 w-4" />Lihat<span class="sr-only"> {{ $file->title }}</span></a>
                            @else
                                <button type="button" class="btn btn-secondary btn-sm disabled:cursor-not-allowed disabled:opacity-50" disabled title="Pratinjau hanya untuk PDF & gambar — silakan unduh"><x-heroicon-o-eye class="h-4 w-4" />Lihat</button>
                            @endif
                            <form method="POST" action="{{ route('admin.files.destroy', $file) }}" data-confirm="Hapus file pendukung “{{ $file->title }}”?" data-confirm-label="Hapus">
                                @csrf @method('DELETE')<button class="btn btn-secondary btn-sm text-bad-ink"><x-heroicon-o-trash class="h-4 w-4" />Hapus</button>
                            </form>
                            <button type="button" class="btn btn-secondary btn-sm" @click="open" :aria-expanded="expanded"><x-heroicon-o-pencil-square class="h-4 w-4" />Ubah<span class="sr-only"> {{ $file->title }}</span></button>
                        </div>
                        <x-modal width="sm:max-w-xl">
                            <x-slot:heading>Ubah file pendukung</x-slot:heading>
                            <form method="POST" action="{{ route('admin.files.update', $file) }}" enctype="multipart/form-data" class="space-y-4">
                                @csrf @method('PUT')
                                <input type="hidden" name="_edit" value="{{ $file->id }}">
                                <div>
                                    <label class="field-label" for="ft-{{ $file->id }}">Judul<span class="text-bad-ink" aria-hidden="true"> *</span></label>
                                    <input id="ft-{{ $file->id }}" name="title" class="input" maxlength="150" value="{{ $editing ? old('title') : $file->title }}" required>
                                    @if ($editing)@error('title')<p class="field-error">{{ $message }}</p>@enderror @endif
                                </div>
                                <div>
                                    <label class="field-label" for="fc-{{ $file->id }}">Kategori<span class="text-bad-ink" aria-hidden="true"> *</span></label>
                                    <input id="fc-{{ $file->id }}" name="category" class="input" maxlength="60" list="categories" value="{{ $editing ? old('category') : $file->category }}" required>
                                    @if ($editing)@error('category')<p class="field-error">{{ $message }}</p>@enderror @endif
                                </div>
                                <div>
                                    <label class="field-label" for="fd-{{ $file->id }}">Deskripsi</label>
                                    <textarea id="fd-{{ $file->id }}" name="description" rows="3" class="input" maxlength="1000">{{ $editing ? old('description') : $file->description }}</textarea>
                                </div>
                                <fieldset>
                                    <legend class="field-label">Role akses melihat<span class="text-bad-ink" aria-hidden="true"> *</span></legend>
                                    <div class="flex flex-wrap gap-x-6 gap-y-2">
                                        @foreach ($roles as $value => $label)
                                            <label class="flex min-h-[40px] cursor-pointer items-center gap-2 text-sm text-ink">
                                                <input type="checkbox" class="checkbox" name="visible_to[]" value="{{ $value }}" @checked(in_array($value, $fileRoles, true))>
                                                {{ $label }}
                                            </label>
                                        @endforeach
                                    </div>
                                    @if ($editing)@error('visible_to')<p class="field-error">{{ $message }}</p>@enderror @endif
                                </fieldset>
                                <div>
                                    <label class="field-label" for="ff-{{ $file->id }}">Ganti file <span class="font-normal text-muted">(opsional)</span></label>
                                    <input id="ff-{{ $file->id }}" type="file" name="file" class="input py-2">
                                    <p class="field-hint">Kosongkan untuk tetap memakai {{ $file->original_name }}.</p>
                                    @if ($editing)@error('file')<p class="field-error">{{ $message }}</p>@enderror @endif
                                </div>
                                <div class="flex justify-end gap-3 border-t border-rule pt-4">
                                    <button type="button" class="btn btn-ghost" @click="close">Batal</button>
                                    <button class="btn btn-primary" data-once>Simpan perubahan</button>
                                </div>
                            </form>
                        </x-modal>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</x-layouts.app>
