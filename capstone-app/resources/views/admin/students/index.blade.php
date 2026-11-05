<x-layouts.app title="Mahasiswa">
    <x-page-head title="Data & akun mahasiswa" lede="Data SKS lulus dan mata kuliah yang sudah lulus dipakai untuk checklist kelayakan dan screening registrasi tim.">
        <x-slot:actions>
            @include('admin._import', ['type' => 'mahasiswa', 'label' => 'mahasiswa', 'button' => true, 'hint' => 'Kolom mk_lulus berisi kode mata kuliah dipisah titik koma. Data dengan NIM/email yang sama diperbarui; untuk NIM yang sudah terdaftar cukup isi nim dan kolom yang ingin diubah (mis. nim + peminatan), kolom kosong tidak mengubah data.'])
            {{-- Tambah mahasiswa: modal di atas halaman; terbuka lagi bila simpan gagal validasi. --}}
            <div x-data="registrationForm" data-open="{{ $errors->any() && old('_student') === 'baru' ? 'true' : 'false' }}">
                <button type="button" class="btn btn-primary" @click="open" :aria-expanded="expanded"><x-heroicon-o-plus class="h-5 w-5" />Tambah mahasiswa</button>
                <x-modal width="sm:max-w-2xl">
                    <x-slot:heading>Tambah mahasiswa</x-slot:heading>
                    @include('admin.students._form', ['student' => new \App\Models\User(['role' => 'mahasiswa'])])
                </x-modal>
            </div>
        </x-slot:actions>
    </x-page-head>

    <div class="panel">
        <div class="panel-head">
            <x-filter-bar :action="route('admin.students.index')">
                <div class="min-w-[12rem] flex-1"><label class="field-label" for="q">Cari</label><input id="q" name="q" value="{{ request('q') }}" class="input" placeholder="Nama, NIM, email"></div>
                <div><label class="field-label" for="tim">Tim</label>
                    <select id="tim" name="tim" class="input"><option value="">Semua</option><option value="belum" @selected(request('tim') === 'belum')>Belum punya tim</option></select></div>
            </x-filter-bar>
        </div>
        @if ($students->isEmpty())
            <x-empty title="Belum ada data mahasiswa" icon="users">Tambahkan manual atau impor dari Excel/CSV.</x-empty>
        @else
            <x-scroll-pager>
                <table class="data-table">
                    <thead><tr><th>NIM</th><th>Nama</th><th>SKS</th><th>Peminatan</th><th>Daerah asal</th><th>Tim</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($students as $s)
                            @php $membership = $s->memberships->first(fn ($m) => $m->team?->period_id === $period?->id); @endphp
                            <tr class="{{ $s->is_active ? '' : 'text-muted' }}">
                                <td class="code">{{ $s->nim }}</td>
                                <td><p class="font-medium">{{ $s->name }}</p><p class="text-caption text-muted">{{ $s->email }}</p></td>
                                <td class="tabular">{{ $s->sks_passed ?? '—' }}</td>
                                <td>{{ $s->specialization ?? '—' }}</td>
                                <td>{{ $s->origin_region ?? '—' }}</td>
                                <td>@if ($membership)<a class="link" href="{{ route('admin.teams.show', $membership->team_id) }}">{{ $membership->team->name }}</a>@else<span class="text-muted">—</span>@endif</td>
                                <td>
                                    <div x-data="registrationForm" data-open="{{ $errors->any() && old('_student') === (string) $s->id ? 'true' : 'false' }}" class="flex justify-end gap-1">
                                        <button type="button" class="btn btn-ghost btn-sm" @click="open" :aria-expanded="expanded">Ubah</button>
                                        @if ($s->is_active)
                                            <form method="POST" action="{{ route('admin.students.toggle', $s) }}" data-confirm="Nonaktifkan akun {{ $s->name }}? Mahasiswa tidak dapat masuk sampai akunnya diaktifkan kembali." data-confirm-label="Nonaktifkan">
                                                @csrf<button class="btn btn-ghost btn-sm text-bad-ink hover:bg-bad-tint">Nonaktifkan</button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('admin.students.toggle', $s) }}">
                                                @csrf<button class="btn btn-ghost btn-sm text-brand-deep">Aktifkan</button>
                                            </form>
                                        @endif
                                        <x-modal width="sm:max-w-2xl">
                                            <x-slot:heading>Ubah data mahasiswa · {{ $s->name }}</x-slot:heading>
                                            @include('admin.students._form', ['student' => $s])
                                        </x-modal>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-scroll-pager>
            {{ $students->links() }}
        @endif
    </div>
</x-layouts.app>
