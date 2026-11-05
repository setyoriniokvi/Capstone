{{-- Instruksi (opsional) untuk jenis topik pada tab aktif; tampil di bagian atas halaman topik mandiri (mahasiswa, tab Topik Tim)
     atau Topik Dosen (dosen). Simpan dengan isian kosong / tombol Hapus = tanpa instruksi. Butuh $tab, $instruction. --}}
@php $label = \App\Models\ForbiddenKeyword::SOURCES[$tab]; @endphp
<section class="panel mb-6" aria-labelledby="instruksi-judul">
    <div class="panel-head">
        <h2 id="instruksi-judul" class="panel-title">Instruksi {{ $label }}</h2>
        <p class="text-sm text-muted">Opsional. Tampil di bagian atas halaman {{ $tab === 'dosen' ? 'Topik Dosen milik dosen' : 'Topik Tim mahasiswa' }}.</p>
    </div>
    <div class="panel-body">
        <form method="POST" action="{{ route('admin.topic-instructions.update', $tab) }}">
            @csrf @method('PUT')
            <label for="instruksi" class="sr-only">Instruksi {{ $label }}</label>
            <textarea id="instruksi" name="body" rows="4" class="input" maxlength="5000" placeholder="Belum ada instruksi. Tulis instruksi untuk {{ $label }} di sini.">{{ old('body', $instruction) }}</textarea>
            @error('body')<p class="field-error">{{ $message }}</p>@enderror
            <div class="mt-3 flex flex-wrap justify-end gap-2">
                <button type="submit" form="hapus-instruksi" class="btn btn-ghost text-bad-ink disabled:cursor-not-allowed disabled:opacity-50" @disabled(! $instruction) @unless ($instruction) title="Belum ada instruksi" @endunless>
                    <x-heroicon-o-trash class="h-5 w-5" /> Hapus
                </button>
                <button class="btn btn-primary">Simpan</button>
            </div>
        </form>
        <form id="hapus-instruksi" method="POST" action="{{ route('admin.topic-instructions.destroy', $tab) }}" data-confirm="Hapus instruksi {{ $label }}?" data-confirm-label="Hapus">@csrf @method('DELETE')</form>
    </div>
</section>
