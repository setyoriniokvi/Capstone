<x-layouts.app title="Mahasiswa" section="Admin · Data mahasiswa">
    <x-page-head title="Data & akun mahasiswa" lede="Data SKS lulus dan mata kuliah yang sudah lulus dipakai untuk checklist kelayakan dan screening registrasi tim." />

    @include('admin._import', ['type' => 'mahasiswa', 'label' => 'mahasiswa', 'hint' => 'Kolom mk_lulus berisi kode mata kuliah dipisah titik koma. Data dengan NIM/email yang sama diperbarui.'])

    <div class="panel">
        <div class="panel-head">
            <x-filter-bar :action="route('admin.students.index')">
                <div><label class="field-label" for="q">Cari</label><input id="q" name="q" value="{{ request('q') }}" class="input" placeholder="Nama, NIM, email"></div>
                <div><label class="field-label" for="prodi">Prodi</label>
                    <select id="prodi" name="prodi" class="input"><option value="">Semua</option>@foreach ($prodis as $p)<option value="{{ $p->code }}" @selected(request('prodi') === $p->code)>{{ $p->code }}</option>@endforeach</select></div>
                <div><label class="field-label" for="tim">Tim</label>
                    <select id="tim" name="tim" class="input"><option value="">Semua</option><option value="belum" @selected(request('tim') === 'belum')>Belum punya tim</option></select></div>
            </x-filter-bar>
        </div>
        @if ($students->isEmpty())
            <x-empty title="Belum ada data mahasiswa" icon="users">Impor akun mahasiswa dari Excel/CSV.</x-empty>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>NIM</th><th>Nama</th><th>Prodi</th><th>SKS</th><th>Peminatan</th><th>Daerah asal</th><th>Tim</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($students as $s)
                            @php $membership = $s->memberships->first(fn ($m) => $m->team?->period_id === $period?->id); @endphp
                            <tr class="{{ $s->is_active ? '' : 'text-muted' }}">
                                <td class="code">{{ $s->nim }}</td>
                                <td><p class="font-medium">{{ $s->name }}</p><p class="text-caption text-muted">{{ $s->email }}</p></td>
                                <td>{{ $s->prodi_code }}</td>
                                <td class="font-mono">{{ $s->sks_passed ?? '—' }}</td>
                                <td>{{ $s->specialization ?? '—' }}</td>
                                <td>{{ $s->origin_region ?? '—' }}</td>
                                <td>@if ($membership)<a class="link code" href="{{ route('admin.teams.show', $membership->team_id) }}">{{ $membership->team->code }}</a>@else<span class="text-muted">—</span>@endif</td>
                                <td><div class="flex justify-end gap-1">
                                    <a href="{{ route('admin.students.edit', $s) }}" class="btn btn-ghost btn-sm">Ubah</a>
                                    <form method="POST" action="{{ route('admin.students.toggle', $s) }}">@csrf<button class="btn btn-ghost btn-sm">{{ $s->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>
                                </div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $students->links() }}
        @endif
    </div>
</x-layouts.app>
