<x-layouts.app title="Dosen">
    <x-page-head title="Data & akun dosen" lede="Dosen masuk dengan email dan password akun yang terdaftar (akun hasil impor: password awal = kode dosen). Setiap dosen berhak akses Dosen. Hak akses Pembimbing dan Penguji tercentang otomatis saat dosen diplot, dan dapat diubah lewat tombol Ubah.">
        <x-slot:actions>
            @include('admin._import', ['type' => 'dosen', 'label' => 'dosen', 'button' => true, 'hint' => 'Data dengan email yang sama diperbarui, bukan diduplikasi.'])
            {{-- Tambah dosen: modal di atas halaman; terbuka lagi bila simpan gagal validasi. --}}
            <div x-data="registrationForm" data-open="{{ $errors->any() && old('_lecturer') === 'baru' ? 'true' : 'false' }}">
                <button type="button" class="btn btn-primary" @click="open" :aria-expanded="expanded"><x-heroicon-o-plus class="h-5 w-5" />Tambah dosen</button>
                <x-modal width="sm:max-w-2xl">
                    <x-slot:heading>Tambah dosen</x-slot:heading>
                    <p class="mb-4 text-sm text-muted">Email dan password dipakai untuk login; email juga dipakai untuk pengiriman reminder. Nomor WhatsApp dipakai untuk notifikasi otomatis.</p>
                    @include('admin.lecturers._form', ['lecturer' => new \App\Models\User(['role' => 'dosen']), 'profile' => new \App\Models\LecturerProfile])
                </x-modal>
            </div>
        </x-slot:actions>
    </x-page-head>

    <div class="panel">
        <div class="panel-head">
            <x-filter-bar :action="route('admin.lecturers.index')">
                <div class="min-w-[12rem] flex-1"><label class="field-label" for="q">Cari</label><input id="q" name="q" value="{{ request('q') }}" class="input" placeholder="Nama, email, kode"></div>
                <div><label class="field-label" for="akses">Hak akses</label>
                    <select id="akses" name="akses" class="input"><option value="">Semua</option>
                        @foreach (['can_supervise' => 'Pembimbing', 'can_examine' => 'Penguji'] as $k => $l)<option value="{{ $k }}" @selected(request('akses') === $k)>{{ $l }}</option>@endforeach
                    </select></div>
            </x-filter-bar>
        </div>
        @if ($lecturers->isEmpty())
            <x-empty title="Belum ada data dosen" icon="academic-cap">Tambahkan manual atau impor dari Excel/CSV.</x-empty>
        @else
            <x-scroll-pager>
                <table class="data-table">
                    <thead><tr><th>Kode</th><th>Nama</th><th>Kontak</th><th>Peminatan</th><th>Hak akses</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($lecturers as $l)
                            <tr>
                                <td class="code font-medium">{{ $l->lecturerProfile?->lecturer_code }}</td>
                                <td><p class="font-medium">{{ $l->name }}</p>@if ($l->lecturerProfile?->nip)<p class="code text-muted">{{ $l->lecturerProfile->nip }}</p>@endif</td>
                                <td><p>{{ $l->email }}</p><p class="code text-muted">{{ $l->phone ?: '— WA belum diisi' }}</p></td>
                                <td>{{ $l->lecturerProfile?->specialization ?? '—' }}</td>
                                <td><div class="flex max-w-[16rem] flex-wrap gap-1">@foreach ($l->lecturerProfile?->accessLabels() ?? ['Dosen'] as $a)<span class="rounded bg-sunken px-1.5 py-0.5 text-caption">{{ $a }}</span>@endforeach</div></td>
                                <td>@if ($l->is_active)<x-badge status="aktif" />@else<x-badge status="nonaktif" />@endif</td>
                                <td>
                                    <div x-data="registrationForm" data-open="{{ $errors->any() && old('_lecturer') === (string) $l->id ? 'true' : 'false' }}" class="flex justify-end gap-1">
                                        <button type="button" class="btn btn-ghost btn-sm" @click="open" :aria-expanded="expanded">Ubah</button>
                                        @if ($l->is_active)
                                            <form method="POST" action="{{ route('admin.lecturers.toggle', $l) }}" data-confirm="Nonaktifkan akun {{ $l->name }}? Dosen tidak dapat masuk sampai akunnya diaktifkan kembali." data-confirm-label="Nonaktifkan">
                                                @csrf<button class="btn btn-ghost btn-sm text-bad-ink hover:bg-bad-tint">Nonaktifkan</button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('admin.lecturers.toggle', $l) }}">
                                                @csrf<button class="btn btn-ghost btn-sm text-brand-deep">Aktifkan</button>
                                            </form>
                                        @endif
                                        <x-modal width="sm:max-w-2xl">
                                            <x-slot:heading>Ubah data dosen · {{ $l->name }}</x-slot:heading>
                                            @include('admin.lecturers._form', ['lecturer' => $l, 'profile' => $l->lecturerProfile ?? new \App\Models\LecturerProfile])
                                        </x-modal>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-scroll-pager>
            {{ $lecturers->links() }}
        @endif
    </div>
</x-layouts.app>
