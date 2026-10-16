<x-layouts.app :title="$student->name" section="Admin · Data mahasiswa">
    <x-page-head title="Ubah data mahasiswa" :lede="$student->nim.' · '.$student->name" />
    <form method="POST" action="{{ route('admin.students.update', $student) }}" class="panel max-w-3xl">
        @csrf @method('PUT')
        <div class="grid gap-4 panel-body sm:grid-cols-2">
            <x-field label="NIM" name="nim" required><input id="nim" name="nim" class="input font-mono" value="{{ old('nim', $student->nim) }}" required></x-field>
            <x-field label="Nama lengkap" name="name" required><input id="name" name="name" class="input" value="{{ old('name', $student->name) }}" required></x-field>
            <x-field label="Email Google" name="email" required><input id="email" type="email" name="email" class="input" value="{{ old('email', $student->email) }}" required></x-field>
            <x-field label="Prodi" name="prodi_code" required>
                <select id="prodi_code" name="prodi_code" class="input">@foreach ($prodis as $p)<option value="{{ $p->code }}" @selected(old('prodi_code', $student->prodi_code) === $p->code)>{{ $p->code }} — {{ $p->name }}</option>@endforeach</select>
            </x-field>
            <x-field label="Nomor WhatsApp" name="phone"><input id="phone" name="phone" inputmode="tel" class="input font-mono" value="{{ old('phone', $student->phone) }}"></x-field>
            <x-field label="SKS lulus" name="sks_passed"><input id="sks_passed" type="number" name="sks_passed" class="input font-mono" value="{{ old('sks_passed', $student->sks_passed) }}"></x-field>
            <x-field label="Mata kuliah lulus" name="passed_courses" hint="Kode dipisah titik koma." class="sm:col-span-2"><input id="passed_courses" name="passed_courses" class="input font-mono" value="{{ old('passed_courses', implode('; ', $student->passed_courses ?? [])) }}"></x-field>
            <x-field label="Daerah asal" name="origin_region"><input id="origin_region" name="origin_region" class="input" value="{{ old('origin_region', $student->origin_region) }}"></x-field>
            <x-field label="Peminatan" name="specialization"><input id="specialization" name="specialization" class="input" value="{{ old('specialization', $student->specialization) }}"></x-field>
        </div>
        <div class="flex justify-end gap-2 border-t border-rule px-4 py-3 sm:px-5">
            <a href="{{ route('admin.students.index') }}" class="btn btn-ghost">Batal</a>
            <button class="btn btn-primary" data-once>Simpan</button>
        </div>
    </form>
</x-layouts.app>
