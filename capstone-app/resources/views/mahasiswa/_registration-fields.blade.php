{{-- Isian registrasi tim (F21): halaman registrasi publik (auth/register — ketua membuat akun & password) dan modal
     Registrasi Tim di Tim Saya ($leader = mahasiswa yang masuk; NIM/nama/email ketua diambil dari akunnya, tanpa password).
     Anggota dikenali dari NIM + email; akun anggota baru dibuat dari isian ini dan diaktifkan lewat tautan atur password
     setelah admin menerima tim. Dari Tim Saya, anggota dipilih (invite) dari mahasiswa yang belum bertim — butuh $students. Butuh $courses, $specializations, $grades, $teamSize dari TeamRegistration::options(). --}}
@php $leader ??= null; @endphp
<x-field label="Nama tim" name="name" required>
    <input id="name" name="name" class="input" value="{{ old('name') }}" required maxlength="150">
</x-field>

{{-- Ketua & anggota: masing-masing satu baris yang dibuka/tutup (<details>) agar formulir ringkas.
     Baris terbuka otomatis bila isiannya gagal validasi; app.js juga membukanya saat isian wajib di dalamnya kosong. --}}
<div class="space-y-3">
    @include('mahasiswa._person-section', ['dot' => 'leader', 'title' => 'Ketua tim', 'optional' => false, 'password' => ! $leader, 'account' => $leader, 'invite' => false])
    @for ($i = 0; $i < $teamSize['max'] - 1; $i++)
        @include('mahasiswa._person-section', ['dot' => "members.$i", 'title' => 'Anggota '.($i + 1), 'optional' => $i >= $teamSize['min'] - 1, 'password' => false, 'account' => null, 'invite' => (bool) $leader])
    @endfor
</div>
@error('members')<p class="field-error">{{ $message }}</p>@enderror
