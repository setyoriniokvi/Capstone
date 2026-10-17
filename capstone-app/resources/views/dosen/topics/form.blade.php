@php $editing = $topic->exists; @endphp
<x-layouts.app :title="$editing ? 'Ubah topik' : 'Topik baru'" section="Dosen · Topik">
    <x-page-head :title="$editing ? 'Ubah topik' : 'Topik baru'" lede="Topik wajib mencakup permasalahan pada sistem terintegrasi. Kuota menentukan jumlah tim yang dapat diterima." />
    <form method="POST" action="{{ $editing ? route('dosen.topics.update', $topic) : route('dosen.topics.store') }}" class="panel max-w-form">
        @csrf
        @if ($editing) @method('PUT') @endif
        <div class="grid gap-4 panel-body sm:grid-cols-[minmax(0,1fr)_8rem]">
            <x-field label="Judul topik" name="title" required class="sm:col-span-2"><input id="title" name="title" class="input" value="{{ old('title', $topic->title) }}" required maxlength="255"></x-field>
            <x-field label="Bidang / kategori" name="field" required><input id="field" name="field" class="input" value="{{ old('field', $topic->field) }}" required></x-field>
            <x-field label="Kuota tim" name="quota" required><input id="quota" type="number" min="1" max="20" name="quota" class="input tabular" value="{{ old('quota', $topic->quota) }}" required></x-field>
            <x-field label="Deskripsi" name="description" required class="sm:col-span-2"><textarea id="description" name="description" rows="8" class="input" required>{{ old('description', $topic->description) }}</textarea></x-field>
        </div>
        <div class="flex justify-end gap-2 border-t border-rule px-4 py-3 sm:px-6">
            <a href="{{ route('dosen.topics.index') }}" class="btn btn-ghost">Batal</a>
            <button class="btn btn-primary" data-once>{{ $editing ? 'Simpan' : 'Kirim ke admin' }}</button>
        </div>
    </form>
</x-layouts.app>
