{{-- Formulir tambah/edit topik di atas halaman Topik Dosen (Alpine "topicForm" pada pembungkus halaman:
     tombol "Tambah Topik" mengosongkan formulir, tombol "Edit" mengisinya dari data-topic — tanpa memuat ulang).
     Kolom mengikuti tabel topik: Topik, Deskripsi, Program Studi → Kompetensi, Instansi/objek, Jumlah tim yang tersedia.
     Butuh $topic (nilai awal), $programs, $prodiSpecializations. --}}
<x-modal>
    <x-slot:heading><span x-text="heading">{{ $topic->exists ? 'Edit topik' : 'Tambah topik' }}</span></x-slot:heading>
    <form x-ref="panel" method="POST" :action="action" action="{{ $topic->exists ? route('dosen.topics.update', $topic) : route('dosen.topics.store') }}">
        @csrf
        <input type="hidden" name="_method" :value="method" value="{{ $topic->exists ? 'PUT' : 'POST' }}">
        <input type="hidden" name="_form_action" :value="action">
        <input type="hidden" name="_form_editing" :value="editingFlag">
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field label="Topik" name="title" required class="sm:col-span-2">
                <input id="title" x-ref="title" name="title" class="input" value="{{ old('title', $topic->title) }}" required maxlength="255">
            </x-field>
            <x-field label="Deskripsi" name="description" required class="sm:col-span-2" hint="Topik wajib mencakup permasalahan pada sistem terintegrasi.">
                <textarea id="description" x-ref="description" name="description" rows="6" class="input" required>{{ old('description', $topic->description) }}</textarea>
            </x-field>
            <x-field label="Program studi" name="prodi_code" required>
                <select id="prodi_code" name="prodi_code" class="input" required>
                    <option value="">Pilih program studi</option>
                    @foreach ($programs as $program)<option value="{{ $program->code }}" @selected(old('prodi_code', $topic->prodi_code) === $program->code)>{{ $program->name }}</option>@endforeach
                </select>
            </x-field>
            {{-- Peminatan dari aturan registrasi periode --}}
            <x-field label="Kompetensi yang diharapkan" name="competencies" required>
                <select id="competencies" name="competencies" class="input" required>
                    <option value="">Pilih peminatan</option>
                    @foreach ($prodiSpecializations as $name)<option value="{{ $name }}" @selected(old('competencies', $topic->competencies) === $name)>{{ $name }}</option>@endforeach
                </select>
            </x-field>
            <x-field label="Instansi / organisasi / perusahaan objek" name="object" required>
                <input id="object" x-ref="object" name="object" class="input" value="{{ old('object', $topic->object) }}" required maxlength="255">
            </x-field>
            <x-field label="Jumlah tim yang tersedia" name="quota" required hint="Kuota tim yang dapat diterima pada topik ini.">
                <input id="quota" x-ref="quota" type="number" min="1" max="20" name="quota" class="input tabular" value="{{ old('quota', $topic->quota) }}" required>
            </x-field>
        </div>
        <div class="mt-5 flex flex-wrap justify-end gap-3 border-t border-rule pt-4">
            <button type="button" class="btn btn-ghost" @click="close">Batal</button>
            <button class="btn btn-primary" data-once x-text="submitLabel">{{ $topic->exists ? 'Simpan perubahan' : 'Kirim ke admin' }}</button>
        </div>
        <p class="mt-3 text-caption text-muted">Topik baru dan perubahan isi topik ditinjau admin sebelum terbit di bank topik; mengubah jumlah tim saja tidak perlu ditinjau ulang.</p>
    </form>
</x-modal>
