{{-- Kelas mahasiswa (di samping Email pada mahasiswa/_person-fields): pilihan dari daftar kelas periode yang diatur admin
     di Aturan registrasi tim. Tidak tampil bila admin belum mengisi daftar kelas. Memakai $dot, $input, $value, $required. --}}
@if (($classes ?? collect())->isNotEmpty())
    <x-field label="Kelas" name="{{ $dot }}.class_name" :required="$required">
        <select id="{{ $dot }}.class_name" name="{{ $input('class_name') }}" class="input" @required($required)>
            <option value="">Pilih kelas</option>
            @foreach ($classes as $class)
                <option value="{{ $class }}" @selected($value('class_name') === $class)>{{ $class }}</option>
            @endforeach
        </select>
    </x-field>
@endif
