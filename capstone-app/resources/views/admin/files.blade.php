<x-layouts.app title="File Pendukung" section="Admin · File pendukung">
    <x-page-head title="File pendukung" lede="Panduan Capstone, slide sosialisasi, dan materi lain yang dapat dibaca mahasiswa dan dosen." />
    <div class="grid gap-6 md:grid-cols-[minmax(0,1fr)_20rem]">
        <section class="panel">
            @if ($files->isEmpty())
                <x-empty title="Belum ada file" icon="folder" />
            @else
                <ul class="divide-y divide-rule">
                    @foreach ($files as $file)
                        <li x-data="disclosure" class="px-4 py-3 sm:px-6">
                            <div class="flex flex-wrap items-center gap-3">
                                <x-heroicon-o-document class="h-6 w-6 shrink-0 text-muted" />
                                <div class="min-w-0 flex-1">
                                    <p class="font-medium">{{ $file->title }}</p>
                                    <p class="text-caption text-muted">{{ $file->category }} · {{ $file->original_name }} · {{ \App\Support\Format::bytes($file->size) }}</p>
                                </div>
                                <a href="{{ route('files.download', $file) }}" class="btn btn-ghost btn-sm">Unduh</a>
                                <button type="button" class="btn btn-ghost btn-sm" @click="toggle">Ubah</button>
                                <form method="POST" action="{{ route('admin.files.destroy', $file) }}" data-confirm="Hapus file pendukung “{{ $file->title }}”?">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm text-bad-ink">Hapus</button></form>
                            </div>
                            <x-modal width="sm:max-w-lg">
                                <x-slot:heading>Ubah file pendukung</x-slot:heading>
                                <form method="POST" action="{{ route('admin.files.update', $file) }}" class="space-y-4">
                                    @csrf @method('PUT')
                                    <div><label class="field-label" for="ft-{{ $file->id }}">Judul</label><input id="ft-{{ $file->id }}" name="title" class="input" value="{{ $file->title }}" required></div>
                                    <div><label class="field-label" for="fc-{{ $file->id }}">Kategori</label><input id="fc-{{ $file->id }}" name="category" class="input" value="{{ $file->category }}" required></div>
                                    <div><label class="field-label" for="fd-{{ $file->id }}">Deskripsi</label><input id="fd-{{ $file->id }}" name="description" class="input" value="{{ $file->description }}"></div>
                                    <div class="flex justify-end gap-3 border-t border-rule pt-4"><button type="button" class="btn btn-ghost" @click="close">Batal</button><button class="btn btn-primary">Simpan</button></div>
                                </form>
                            </x-modal>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
        <section class="panel h-fit">
            <div class="panel-head"><h2 class="panel-title">Unggah file</h2></div>
            <form method="POST" action="{{ route('admin.files.store') }}" enctype="multipart/form-data" class="panel-body space-y-4">
                @csrf
                <x-field label="Judul" name="title" required><input id="title" name="title" class="input" value="{{ old('title') }}" required></x-field>
                <x-field label="Kategori" name="category" required hint="Contoh: Panduan, Slide Sosialisasi, Template">
                    <input id="category" name="category" class="input" list="categories" value="{{ old('category') }}" required>
                    <datalist id="categories">@foreach ($categories as $c)<option value="{{ $c }}">@endforeach</datalist>
                </x-field>
                <x-field label="Deskripsi" name="description"><textarea id="description" name="description" rows="2" class="input">{{ old('description') }}</textarea></x-field>
                <x-field label="File" name="file" required hint="PDF, DOCX, PPTX, XLSX, gambar, atau ZIP. Maks. 50 MB."><input id="file" type="file" name="file" class="input py-2" required></x-field>
                <button class="btn btn-primary w-full" data-once>Unggah</button>
            </form>
        </section>
    </div>
</x-layouts.app>
