{{-- Modal Registrasi Tim di Tim Saya — bagi mahasiswa yang sudah punya akun tetapi belum bertim (mis. timnya terhapus admin).
     Isiannya sama dengan registrasi publik (mahasiswa/_registration-fields); identitas ketua diambil dari akun yang masuk dan
     anggota dipilih (invite) dari mahasiswa yang belum bertim ($students). Butuh pilihan dari TeamRegistration::options(). --}}
@php
    $pickerOptions = $students->map(fn ($s) => [
        'id' => $s->id, 'name' => $s->name, 'nim' => (string) $s->nim, 'email' => $s->email,
        'specialization' => $s->specialization, 'region' => $s->origin_region,
    ])->values();
@endphp
<script type="application/json" id="data-mahasiswa">{!! $pickerOptions->toJson(JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>

<x-modal>
    <x-slot:heading>Registrasi tim</x-slot:heading>

    <form method="POST" action="{{ route('mahasiswa.team.store') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @include('mahasiswa._registration-fields', ['leader' => auth()->user()])

        <div class="flex flex-wrap items-center justify-end gap-3 border-t border-rule pt-5">
            <button type="button" class="btn btn-ghost" @click="close">Batal</button>
            <button class="btn btn-primary" data-once>Daftar Tim</button>
        </div>
    </form>
</x-modal>
