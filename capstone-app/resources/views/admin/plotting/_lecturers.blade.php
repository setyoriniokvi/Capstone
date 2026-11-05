{{-- Tab Plotting Dosen: ringkasan flag, lalu tabel load balancing dosen. Di bawah judul tabel: cari nama / kode dosen +
     tombol "Filter" (peminatan, sumber kuota, status bimbing, status uji) yang menyaring baris langsung di browser
     (Alpine "tableFilter"). "Detail Plottingan" membuka modal per dosen: kolom Tim bimbingan & Tim uji masing-masing
     berisi status + kuota, kartu tim yang sudah diplot (nama + peminatan, tombol Hapus), lalu "Tambah tim" — cari +
     filter peminatan (centang; tanpa centang = semua tim, termasuk yang berbeda peminatan dengan dosen) dan daftar tim
     ber-topik final dengan tombol Tambah. Butuh $rows, $teams. --}}
@php
    $roleTitles = ['pembimbing' => 'Tim bimbingan', 'penguji' => 'Tim uji'];
    $roleShort = ['pembimbing' => 'bimbingan', 'penguji' => 'uji'];
    $holder = fn ($team, string $role) => $role === 'pembimbing' ? $team->supervisor : $team->examiner;
    $sources = ['global' => 'Global', 'dosen' => 'Dosen tertentu', 'belum diatur' => 'Belum diatur'];
    $flags = ['OK' => 'OK', 'UNDER' => 'UNDER', 'OVER' => 'OVER'];
    $chip = 'rounded-md border border-rule bg-sunken px-2 py-0.5 text-caption font-medium text-muted';
    $teamSpecs = $teams->mapWithKeys(fn ($t) => [$t->id => \App\Http\Controllers\Admin\PlottingController::teamSpecializations($t)]);
    $allTeamSpecs = $teamSpecs->flatten()->unique()->sort()->values();
@endphp
{{-- Ringkasan flag: UNDER/OVER bila status bimbing atau uji demikian (satu dosen bisa masuk keduanya); OK bila keduanya OK --}}
@php $flagged = fn (string $flag) => $rows->filter(fn ($r) => in_array($flag, [$r['flags']['pembimbing'], $r['flags']['penguji']], true))->count(); @endphp
<x-stat-row class="mb-6" :stats="[
    ['value' => $flagged('UNDER'), 'label' => 'Dosen status bimbing/uji UNDER'],
    ['value' => $flagged('OVER'), 'label' => 'Dosen status bimbing/uji OVER'],
    ['value' => $rows->filter(fn ($r) => $r['flags']['pembimbing'] === 'OK' && $r['flags']['penguji'] === 'OK')->count(), 'label' => 'Dosen status bimbing dan uji OK'],
]" />

<section x-data="tableFilter" data-table-filter class="panel">
    <div class="panel-head">
        <h2 class="panel-title">Load balancing dosen</h2>
        <span class="text-sm text-muted"><span class="tabular font-medium text-ink" x-text="count">{{ $rows->count() }}</span> dari {{ $rows->count() }} dosen</span>
    </div>
    {{-- Cari & filter: menyaring baris langsung di browser tanpa memuat ulang halaman --}}
    <div class="flex flex-wrap items-end gap-3 border-b border-rule px-5 py-4 sm:px-6">
        <div class="min-w-[14rem] flex-1">
            <label class="field-label" for="plot-dosen-cari">Cari</label>
            <div class="relative">
                <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-faint" />
                <input id="plot-dosen-cari" type="search" class="input pl-9" placeholder="Nama atau kode dosen" autocomplete="off" @input="search">
            </div>
        </div>
        @include('admin.plotting._filter-menu', ['panelId' => 'plot-dosen-filter', 'groups' => [
            'specialization' => ['Peminatan', $rows->pluck('lecturer.lecturerProfile.specialization')->filter()->unique()->sort()->mapWithKeys(fn ($s) => [$s => $s])->all()],
            'quotaSource' => ['Sumber kuota', collect($sources)->only($rows->pluck('quota.source')->unique()->all())->all()],
            'supervise' => ['Status bimbing', $flags],
            'examine' => ['Status uji', $flags],
        ]])
    </div>
    <x-scroll-pager>
        <table class="data-table">
            <thead><tr><th>Dosen</th><th class="text-center">Dibimbing / maks</th><th class="text-center">Sisa bimbing</th><th class="text-center">Status bimbing</th><th class="text-center">Diuji / maks</th><th class="text-center">Sisa uji</th><th class="text-center">Status uji</th><th class="text-center">Sumber kuota</th><th><span class="sr-only">Aksi</span></th></tr></thead>
            <tbody>
                @forelse ($rows as $r)
                    @php
                        $lecturer = $r['lecturer'];
                        $reopen = session('plot_lecturer') === $lecturer->id;
                    @endphp
                    <tr x-data="registrationForm" data-open="{{ $reopen ? 'true' : 'false' }}" data-row
                        data-search="{{ mb_strtolower($lecturer->name.' '.$lecturer->lecturerProfile->lecturer_code) }}"
                        data-specialization="{{ $lecturer->lecturerProfile->specialization }}" data-quota-source="{{ $r['quota']['source'] }}"
                        data-supervise="{{ $r['flags']['pembimbing'] }}" data-examine="{{ $r['flags']['penguji'] }}">
                        <td><p class="font-medium">{{ $lecturer->name }}</p><p class="text-caption text-muted"><span class="code">{{ $lecturer->lecturerProfile->lecturer_code }}</span></p></td>
                        @foreach (['pembimbing', 'penguji'] as $role)
                            <td class="text-center tabular">{{ $r['counts'][$role] }} / {{ $r['quota'][$role]['max'] }}<span class="block text-caption text-muted">min {{ $r['quota'][$role]['min'] }}</span></td>
                            <td class="text-center tabular font-medium">{{ $r['remaining'][$role] }}</td>
                            <td class="text-center"><x-flag :value="$r['flags'][$role]" /></td>
                        @endforeach
                        <td class="whitespace-nowrap text-center text-sm text-muted">{{ $sources[$r['quota']['source']] ?? $r['quota']['source'] }}</td>
                        <td class="text-right">
                            <button type="button" class="btn btn-secondary btn-sm whitespace-nowrap" @click="open" :aria-expanded="expanded">
                                Detail Plottingan
                            </button>
                            <x-modal width="sm:max-w-4xl">
                                <x-slot:heading>Detail Plottingan · {{ $lecturer->name }}</x-slot:heading>
                                <div class="space-y-4 text-left">
                                    <p class="flex flex-wrap items-center gap-2 text-sm text-muted">
                                        Peminatan dosen <span class="{{ $chip }}">{{ $lecturer->lecturerProfile->specialization ?? 'Tanpa peminatan' }}</span>
                                    </p>
                                    @if ($reopen)@error('lecturer_id')<p class="field-error">{{ $message }}</p>@enderror @endif
                                    <div class="grid gap-6 md:grid-cols-2">
                                        @foreach ($roleTitles as $role => $title)
                                            @php
                                                $other = $role === 'pembimbing' ? 'penguji' : 'pembimbing';
                                                $mine = $teams->filter(fn ($t) => $holder($t, $role)?->lecturer_id === $lecturer->id);
                                                $candidates = $teams->reject(fn ($t) => $holder($t, $role)?->lecturer_id === $lecturer->id);
                                                $listId = "plot-{$lecturer->id}-{$role}";
                                            @endphp
                                            <section class="min-w-0" aria-labelledby="{{ $listId }}-judul">
                                                <div class="flex items-center gap-2">
                                                    <h3 id="{{ $listId }}-judul" class="font-semibold text-ink">{{ $title }}</h3>
                                                    <x-flag :value="$r['flags'][$role]" />
                                                </div>
                                                <p class="mt-1 text-caption text-muted">Kuota maksimum <span class="tabular font-medium text-ink">{{ $r['quota'][$role]['max'] }}</span> · terisi <span class="tabular font-medium text-ink">{{ $r['counts'][$role] }}</span> · sisa <span class="tabular font-medium text-ink">{{ $r['remaining'][$role] }}</span></p>

                                                {{-- Tim yang sudah diplot ke dosen ini --}}
                                                <ul class="mt-3 space-y-2">
                                                    @forelse ($mine as $team)
                                                        <li class="flex items-start justify-between gap-3 rounded-lg border border-rule bg-surface px-3 py-2.5">
                                                            <span class="min-w-0">
                                                                <span class="block text-sm font-medium text-ink">{{ $team->name }}</span>
                                                                <span class="mt-1 flex flex-wrap gap-1">
                                                                    @forelse ($teamSpecs[$team->id] as $s)<span class="{{ $chip }}">{{ $s }}</span>@empty<span class="text-caption text-muted">Peminatan belum diisi</span>@endforelse
                                                                </span>
                                                            </span>
                                                            <form method="POST" action="{{ route('admin.plotting.destroy', $holder($team, $role)) }}" data-confirm="Hapus {{ $team->name }} dari {{ $roleShort[$role] }} {{ $lecturer->name }}?" data-confirm-label="Hapus">
                                                                @csrf @method('DELETE')<input type="hidden" name="return" value="dosen">
                                                                <button class="btn btn-ghost btn-sm shrink-0 text-bad-ink"><x-heroicon-o-trash class="h-4 w-4" /> Hapus</button>
                                                            </form>
                                                        </li>
                                                    @empty
                                                        <li class="rounded-lg border border-dashed border-rule px-3 py-3 text-sm text-muted">Belum ada {{ strtolower($title) }}.</li>
                                                    @endforelse
                                                </ul>

                                                {{-- Tambah tim: cari + filter peminatan (tanpa centang = semua tim) --}}
                                                <div x-data="tableFilter" data-table-filter class="mt-4 rounded-lg border border-rule bg-sunken p-3">
                                                    <p class="text-sm font-semibold text-ink">Tambah {{ strtolower($title) }}</p>
                                                    <div class="mt-2 flex gap-2">
                                                        <div class="relative min-w-0 flex-1">
                                                            <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-faint" />
                                                            <input type="search" class="input pl-9" placeholder="Cari nama tim" autocomplete="off" @input="search" aria-label="Cari nama tim">
                                                        </div>
                                                        <button type="button" class="btn btn-secondary shrink-0" @click="toggleMenu" :aria-expanded="menuOpen" aria-controls="{{ $listId }}-filter">
                                                            <x-heroicon-o-funnel class="h-5 w-5" /><span class="sr-only sm:not-sr-only">Peminatan</span>
                                                            <span x-show="hasActive" x-cloak class="rounded-full bg-brand px-2 text-caption font-semibold leading-5 text-white tabular" x-text="active"></span>
                                                        </button>
                                                    </div>
                                                    <div id="{{ $listId }}-filter" x-show="menuOpen" x-cloak class="mt-2 rounded-lg border border-rule bg-surface p-2">
                                                        <div class="flex items-center justify-between gap-2 px-1 pb-1">
                                                            <p class="text-caption font-semibold uppercase tracking-wide text-muted">Filter peminatan tim</p>
                                                            <button type="button" class="link text-caption disabled:cursor-not-allowed disabled:opacity-50" @click="reset" :disabled="! hasActive">Reset</button>
                                                        </div>
                                                        @forelse ($allTeamSpecs as $s)
                                                            <label class="flex min-h-[36px] cursor-pointer items-center gap-2.5 rounded-md px-2 text-sm text-ink hover:bg-sunken">
                                                                <input type="checkbox" class="checkbox" value="{{ $s }}" data-filter-group="specializations" @change="pick">
                                                                <span>{{ $s }}</span>
                                                            </label>
                                                        @empty
                                                            <p class="px-2 text-sm text-muted">Belum ada peminatan</p>
                                                        @endforelse
                                                    </div>
                                                    <ul class="mt-2 max-h-64 divide-y divide-rule overflow-y-auto rounded-lg border border-rule bg-surface">
                                                        @forelse ($candidates as $team)
                                                            @php
                                                                $blocked = $holder($team, $other)?->lecturer_id === $lecturer->id;
                                                                $current = $holder($team, $role)?->lecturer;
                                                            @endphp
                                                            <li data-row data-search="{{ mb_strtolower($team->name) }}" data-specializations="{{ implode('|', $teamSpecs[$team->id]) }}"
                                                                class="flex items-start justify-between gap-3 px-3 py-2.5 {{ $blocked ? 'opacity-60' : '' }}">
                                                                <span class="min-w-0">
                                                                    <span class="block text-sm font-medium text-ink">{{ $team->name }}</span>
                                                                    <span class="mt-1 flex flex-wrap gap-1">
                                                                        @forelse ($teamSpecs[$team->id] as $s)<span class="{{ $chip }}">{{ $s }}</span>@empty<span class="text-caption text-muted">Peminatan belum diisi</span>@endforelse
                                                                    </span>
                                                                    @if ($blocked)
                                                                        <span class="mt-1 block text-caption text-muted">Sudah menjadi {{ $other }} tim ini</span>
                                                                    @elseif ($current)
                                                                        <span class="mt-1 block text-caption text-muted">{{ ucfirst($role) }} saat ini: {{ $current->name }} (akan diganti)</span>
                                                                    @endif
                                                                </span>
                                                                <form method="POST" action="{{ route('admin.plotting.team', $team) }}">
                                                                    @csrf
                                                                    <input type="hidden" name="role" value="{{ $role }}">
                                                                    <input type="hidden" name="lecturer_id" value="{{ $lecturer->id }}">
                                                                    <input type="hidden" name="return" value="dosen">
                                                                    <button class="btn btn-secondary btn-sm shrink-0 disabled:cursor-not-allowed disabled:opacity-50" data-once @disabled($blocked)><x-heroicon-o-plus class="h-4 w-4" /> Tambah</button>
                                                                </form>
                                                            </li>
                                                        @empty
                                                            <li class="px-3 py-3 text-sm text-muted">Tidak ada tim lain yang bertopik final.</li>
                                                        @endforelse
                                                    </ul>
                                                    <p x-show="empty" x-cloak class="mt-2 text-sm text-muted">Tidak ada tim yang cocok dengan pencarian atau filter.</p>
                                                </div>
                                            </section>
                                        @endforeach
                                    </div>
                                    <div class="flex justify-end border-t border-rule pt-4">
                                        <button type="button" class="btn btn-primary" @click="close">Selesai</button>
                                    </div>
                                </div>
                            </x-modal>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9"><x-empty title="Belum ada dosen aktif" icon="academic-cap" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </x-scroll-pager>
    <p x-show="empty" x-cloak class="border-t border-rule px-5 py-5 text-sm text-muted sm:px-6">Tidak ada dosen yang cocok dengan pencarian atau filter.</p>
</section>
