<x-layouts.app title="Dosen" section="Admin · Data dosen">
    <x-page-head title="Data & akun dosen" lede="Dosen masuk dengan email dan password akun yang terdaftar (password awal = kode dosen). Hak akses menentukan peran sebagai pengusul topik, pembimbing, penguji, atau dosen kelas.">
        <x-slot:actions><a href="{{ route('admin.lecturers.create') }}" class="btn btn-primary"><x-heroicon-o-plus class="h-5 w-5" />Tambah dosen</a></x-slot:actions>
    </x-page-head>

    @include('admin._import', ['type' => 'dosen', 'label' => 'dosen', 'hint' => 'Isi hak_akses dengan pengusul, pembimbing, penguji, dosen_kelas (pisahkan dengan titik koma). Data dengan email yang sama diperbarui, bukan diduplikasi.'])

    <div class="panel">
        <div class="panel-head">
            <x-filter-bar :action="route('admin.lecturers.index')">
                <div><label class="field-label" for="q">Cari</label><input id="q" name="q" value="{{ request('q') }}" class="input" placeholder="Nama, email, kode"></div>
                <div><label class="field-label" for="prodi">Prodi</label>
                    <select id="prodi" name="prodi" class="input"><option value="">Semua</option>@foreach ($prodis as $p)<option value="{{ $p->code }}" @selected(request('prodi') === $p->code)>{{ $p->code }}</option>@endforeach</select></div>
                <div><label class="field-label" for="akses">Hak akses</label>
                    <select id="akses" name="akses" class="input"><option value="">Semua</option>
                        @foreach (['can_propose_topic' => 'Pengusul topik', 'can_supervise' => 'Pembimbing', 'can_examine' => 'Penguji', 'is_class_lecturer' => 'Dosen kelas'] as $k => $l)<option value="{{ $k }}" @selected(request('akses') === $k)>{{ $l }}</option>@endforeach
                    </select></div>
            </x-filter-bar>
        </div>
        @if ($lecturers->isEmpty())
            <x-empty title="Belum ada data dosen" icon="academic-cap">Tambahkan manual atau impor dari Excel/CSV.</x-empty>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Kode</th><th>Nama</th><th>Kontak</th><th>Prodi</th><th>Peminatan</th><th>Hak akses</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($lecturers as $l)
                            <tr>
                                <td class="code font-medium">{{ $l->lecturerProfile?->lecturer_code }}</td>
                                <td><p class="font-medium">{{ $l->name }}</p>@if ($l->lecturerProfile?->nip)<p class="code text-muted">{{ $l->lecturerProfile->nip }}</p>@endif</td>
                                <td><p>{{ $l->email }}</p><p class="code text-muted">{{ $l->phone ?: '— WA belum diisi' }}</p></td>
                                <td>{{ $l->prodi_code ?? '—' }}</td>
                                <td>{{ $l->lecturerProfile?->specialization ?? '—' }}</td>
                                <td><div class="flex max-w-[16rem] flex-wrap gap-1">@foreach ($l->lecturerProfile?->accessLabels() ?? [] as $a)<span class="rounded bg-sunken px-1.5 py-0.5 text-caption">{{ $a }}</span>@endforeach</div></td>
                                <td>@if ($l->is_active)<x-badge status="aktif" />@else<x-badge status="nonaktif" />@endif</td>
                                <td>
                                    <div class="flex justify-end gap-1">
                                        <a href="{{ route('admin.lecturers.edit', $l) }}" class="btn btn-ghost btn-sm">Ubah</a>
                                        <form method="POST" action="{{ route('admin.lecturers.toggle', $l) }}">@csrf<button class="btn btn-ghost btn-sm">{{ $l->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $lecturers->links() }}
        @endif
    </div>
</x-layouts.app>
