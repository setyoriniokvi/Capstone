@php $editing = $lecturer->exists; @endphp
<x-layouts.app :title="$editing ? $lecturer->name : 'Tambah dosen'" section="Admin · Data dosen">
    <x-page-head :title="$editing ? 'Ubah data dosen' : 'Tambah dosen'" lede="Email dan password dipakai untuk login; email juga dipakai untuk pengiriman reminder. Nomor WhatsApp dipakai untuk notifikasi otomatis." />
    <form method="POST" action="{{ $editing ? route('admin.lecturers.update', $lecturer) : route('admin.lecturers.store') }}" class="panel max-w-3xl">
        @csrf
        @if ($editing) @method('PUT') @endif
        <div class="grid gap-4 panel-body sm:grid-cols-2">
            <x-field label="Nama lengkap" name="name" required class="sm:col-span-2"><input id="name" name="name" class="input" value="{{ old('name', $lecturer->name) }}" required></x-field>
            <x-field label="Kode dosen" name="lecturer_code" required><input id="lecturer_code" name="lecturer_code" class="input font-mono uppercase" value="{{ old('lecturer_code', $profile->lecturer_code) }}" required maxlength="10"></x-field>
            <x-field label="NIP" name="nip"><input id="nip" name="nip" class="input font-mono" value="{{ old('nip', $profile->nip) }}"></x-field>
            <x-field label="Email" name="email" required><input id="email" type="email" name="email" class="input" value="{{ old('email', $lecturer->email) }}" required></x-field>
            <x-field label="Nomor WhatsApp" name="phone" hint="Contoh: 081234567890"><input id="phone" name="phone" inputmode="tel" class="input font-mono" value="{{ old('phone', $lecturer->phone) }}"></x-field>
            <x-field label="Prodi" name="prodi_code">
                <select id="prodi_code" name="prodi_code" class="input"><option value="">—</option>@foreach ($prodis as $p)<option value="{{ $p->code }}" @selected(old('prodi_code', $lecturer->prodi_code) === $p->code)>{{ $p->code }} — {{ $p->name }}</option>@endforeach</select>
            </x-field>
            <x-field label="Peminatan / bidang keahlian" name="specialization"><input id="specialization" name="specialization" class="input" value="{{ old('specialization', $profile->specialization) }}"></x-field>
            <x-field :label="$editing ? 'Password baru' : 'Password'" name="password" :hint="$editing ? 'Kosongkan bila tidak diubah.' : 'Kosongkan untuk memakai kode dosen sebagai password awal.'"><x-password-input id="password" name="password" autocomplete="new-password" minlength="6" /></x-field>
            <fieldset class="sm:col-span-2">
                <legend class="field-label">Hak akses</legend>
                <div class="grid gap-x-6 sm:grid-cols-2">
                    @foreach (['can_propose_topic' => 'Pengusul topik', 'can_supervise' => 'Pembimbing', 'can_examine' => 'Penguji', 'is_class_lecturer' => 'Dosen kelas (pengampu)'] as $k => $l)
                        <label class="flex min-h-[44px] items-center gap-3"><input type="checkbox" class="checkbox" name="{{ $k }}" value="1" @checked(old($k, $profile->{$k}))><span>{{ $l }}</span></label>
                    @endforeach
                </div>
            </fieldset>
        </div>
        <div class="flex justify-end gap-2 border-t border-rule px-4 py-3 sm:px-5">
            <a href="{{ route('admin.lecturers.index') }}" class="btn btn-ghost">Batal</a>
            <button class="btn btn-primary" data-once>Simpan</button>
        </div>
    </form>
</x-layouts.app>
