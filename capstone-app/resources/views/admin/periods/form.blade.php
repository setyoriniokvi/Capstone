@php
    $editing = $period->exists;
    $dt = fn ($v) => $v ? $v->format('Y-m-d\TH:i') : '';
    $components = collect(config('capstone.components'))->except('peer_review');
@endphp
<x-layouts.app :title="$editing ? $period->name : 'Periode baru'" section="Admin · Periode">
    <x-page-head :title="$editing ? $period->name : 'Periode baru'" lede="Atur rentang periode, jendela penilaian, folder Google Drive per prodi, serta milestone dan tenggat setiap tahapan.">
        <x-slot:actions>
            <a href="{{ route('admin.periods.index') }}" class="btn btn-ghost">Kembali</a>
            @if ($editing && ! $period->is_active)
                <form method="POST" action="{{ route('admin.periods.activate', $period) }}">@csrf<button class="btn btn-secondary">Jadikan aktif</button></form>
            @endif
        </x-slot:actions>
    </x-page-head>

    <form method="POST" action="{{ $editing ? route('admin.periods.update', $period) : route('admin.periods.store') }}" class="space-y-6">
        @csrf
        @if ($editing) @method('PUT') @endif

        <section class="panel">
            <div class="panel-head"><h2 class="panel-title">Identitas periode</h2></div>
            <div class="panel-body grid gap-4 sm:grid-cols-2">
                <x-field label="Nama periode" name="name" required class="sm:col-span-2">
                    <input id="name" name="name" class="input" value="{{ old('name', $period->name) }}" placeholder="Capstone Ganjil 2026/2027" required>
                </x-field>
                <x-field label="Tahun akademik" name="academic_year" required hint="Format 2026/2027">
                    <input id="academic_year" name="academic_year" class="input font-mono" value="{{ old('academic_year', $period->academic_year) }}" required>
                </x-field>
                <x-field label="Semester" name="semester" required>
                    <select id="semester" name="semester" class="input">
                        @foreach (['ganjil' => 'Ganjil', 'genap' => 'Genap'] as $v => $l)
                            <option value="{{ $v }}" @selected(old('semester', $period->semester) === $v)>{{ $l }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field label="Tanggal mulai (minggu ke-1)" name="starts_on" required>
                    <input type="date" id="starts_on" name="starts_on" class="input" value="{{ old('starts_on', $period->starts_on?->format('Y-m-d')) }}" required>
                </x-field>
                <x-field label="Tanggal selesai" name="ends_on" required>
                    <input type="date" id="ends_on" name="ends_on" class="input" value="{{ old('ends_on', $period->ends_on?->format('Y-m-d')) }}" required>
                </x-field>
            </div>
        </section>

        <section class="panel">
            <div class="panel-head">
                <h2 class="panel-title">Jendela pengisian nilai</h2>
                <p class="text-sm text-muted">Dipakai untuk pengingat dosen dan tahap aktif QR penilaian.</p>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Bentuk penilaian</th><th>Penilai</th><th>Dibuka</th><th>Tenggat</th></tr></thead>
                    <tbody>
                        @foreach ($components as $key => $meta)
                            @php $w = $period->assessmentWindows?->firstWhere('component', $key); @endphp
                            <tr>
                                <td class="font-medium">{{ $meta['label'] }}</td>
                                <td class="capitalize text-muted">{{ $meta['role'] }}</td>
                                <td><label class="sr-only" for="w-{{ $key }}-o">Dibuka {{ $meta['label'] }}</label><input id="w-{{ $key }}-o" type="datetime-local" name="windows[{{ $key }}][opens_at]" class="input min-w-[13rem]" value="{{ old("windows.$key.opens_at", $dt($w?->opens_at)) }}"></td>
                                <td><label class="sr-only" for="w-{{ $key }}-d">Tenggat {{ $meta['label'] }}</label><input id="w-{{ $key }}-d" type="datetime-local" name="windows[{{ $key }}][due_at]" class="input min-w-[13rem]" value="{{ old("windows.$key.due_at", $dt($w?->due_at)) }}"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel">
            <div class="panel-head">
                <h2 class="panel-title">Folder Google Drive</h2>
                <p class="text-sm text-muted">Isi ID folder (bagian akhir URL folder). Folder per mahasiswa dibuat otomatis di dalamnya.</p>
            </div>
            <div class="panel-body grid gap-4 sm:grid-cols-2">
                <x-field label="Folder induk (cadangan)" name="drive_root_folder_id" class="sm:col-span-2" hint="Dipakai bila prodi belum memiliki folder sendiri.">
                    <input id="drive_root_folder_id" name="drive_root_folder_id" class="input font-mono" value="{{ old('drive_root_folder_id', $period->drive_root_folder_id) }}">
                </x-field>
                @foreach ($prodis as $prodi)
                    <x-field :label="'Prodi '.$prodi->code.' — '.$prodi->name" :name="'folders.'.$prodi->code">
                        <input id="folders.{{ $prodi->code }}" name="folders[{{ $prodi->code }}]" class="input font-mono" value="{{ old("folders.{$prodi->code}", $period->driveFolders?->firstWhere('prodi_code', $prodi->code)?->folder_id) }}">
                    </x-field>
                @endforeach
            </div>
        </section>

        <div class="flex justify-end"><button class="btn btn-primary" data-once>{{ $editing ? 'Simpan periode' : 'Buat periode' }}</button></div>
    </form>

    @if ($editing)
        <section class="panel mt-8">
            <div class="panel-head">
                <h2 class="panel-title">Milestone & tenggat</h2>
                <span class="text-sm text-muted">{{ $period->milestones->count() }} milestone</span>
            </div>
            @if ($period->milestones->isNotEmpty())
                <div class="panel-body border-b border-rule"><x-week-ruler :period="$period" /></div>
                <ul class="divide-y divide-rule">
                    @foreach ($period->milestones as $m)
                        <li x-data="disclosure" class="px-4 py-3 sm:px-5">
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                                <span class="w-14 shrink-0 font-mono text-sm text-muted">{{ $m->week_no ? 'Mg '.$m->week_no : '—' }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="font-medium">{{ $m->name }}</p>
                                    <p class="text-sm text-muted">{{ $m->kindLabel() }}@if ($m->document_type) · {{ config('capstone.document_types.'.$m->document_type) }}@endif · {{ $m->starts_at->translatedFormat('j M H:i') }} → {{ $m->due_at->translatedFormat('j M Y H:i') }}</p>
                                </div>
                                <button type="button" class="btn btn-ghost btn-sm" @click="toggle" :aria-expanded="expanded">Ubah</button>
                                <form method="POST" action="{{ route('admin.milestones.destroy', $m) }}">@csrf @method('DELETE')
                                    <button class="btn btn-ghost btn-sm text-bad-ink" aria-label="Hapus {{ $m->name }}">Hapus</button>
                                </form>
                            </div>
                            <div x-cloak x-show="expanded" class="mt-4">
                                @include('admin.periods._milestone-fields', ['m' => $m, 'action' => route('admin.milestones.update', $m), 'method' => 'PUT', 'submit' => 'Simpan milestone'])
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
            <div class="border-t border-rule bg-sunken px-4 py-4 sm:px-5">
                <h3 class="mb-3 text-sm font-semibold">Tambah milestone</h3>
                @include('admin.periods._milestone-fields', ['m' => new \App\Models\Milestone(['kind' => 'dokumen']), 'action' => route('admin.milestones.store', $period), 'method' => 'POST', 'submit' => 'Tambah'])
            </div>
        </section>

        @if ($period->teams()->doesntExist())
            <form method="POST" action="{{ route('admin.periods.destroy', $period) }}" class="mt-6 flex justify-end">
                @csrf @method('DELETE')
                <button class="btn btn-ghost text-bad-ink">Hapus periode ini</button>
            </form>
        @endif
    @endif
</x-layouts.app>
