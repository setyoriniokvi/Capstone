{{-- Tab Plotting Tim Mahasiswa: Nama tim · Anggota · Peminatan · Topik Final · Status topik (Topik Dosen / Topik Mandiri)
     · Dosen Pembimbing · Dosen Penguji. Hanya tim yang sudah memiliki topik final.
     Peran yang belum terisi: tombol "Plot dosen". Peran yang sudah terisi (termasuk pembimbing otomatis dari topik dosen):
     nama dosen + Edit / Hapus. Modal plot hanya menawarkan dosen yang peminatannya sama dengan peminatan anggota tim
     (semua dosen bila anggota belum mengisi peminatan); dosen yang sudah memegang peran lain di tim ini dinonaktifkan.
     Di bawah judul: cari (nama tim / anggota / NIM / topik / dosen) + satu tombol "Filter" berisi kotak centang peminatan,
     jenis topik, dan dosen yang belum diplot — menyaring baris langsung di browser (Alpine "tableFilter").
     Butuh $teams, $rows, $balance, $greenHead, $cells. --}}
@php
    $leaderChip = 'ml-1.5 rounded-md border border-rule bg-sunken px-2 py-0.5 text-caption font-medium text-muted inline-block whitespace-nowrap';
    $chip = 'rounded-md border border-rule bg-sunken px-2 py-0.5 text-caption font-medium text-muted';
    $holder = fn ($team, string $role) => $role === 'pembimbing' ? $team->supervisor : $team->examiner;
    $teamSpecs = $teams->mapWithKeys(fn ($t) => [$t->id => \App\Http\Controllers\Admin\PlottingController::teamSpecializations($t)]);
@endphp
<section x-data="tableFilter" data-table-filter class="panel overflow-hidden" aria-labelledby="plot-tim-judul">
    <div class="flex items-center justify-between gap-3 px-5 py-4 sm:px-6">
        <h2 id="plot-tim-judul" class="text-lg font-semibold text-ink">Tim mahasiswa</h2>
        <span class="text-sm text-muted"><span class="tabular font-medium text-ink" x-text="count">{{ $teams->count() }}</span> dari {{ $teams->count() }} tim bertopik final</span>
    </div>

    @if ($teams->isEmpty())
        <div class="border-t border-rule"><x-empty title="Belum ada tim yang memiliki topik final" icon="user-group">Tim muncul di sini setelah topik dosen atau topik mandirinya diterima.</x-empty></div>
    @else
        {{-- Cari & filter: menyaring baris langsung di browser tanpa memuat ulang halaman --}}
        <div class="flex flex-wrap items-end gap-3 border-t border-rule px-5 py-4 sm:px-6">
            <div class="min-w-[14rem] flex-1">
                <label class="field-label" for="plot-cari">Cari</label>
                <div class="relative">
                    <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-faint" />
                    <input id="plot-cari" type="search" class="input pl-9" placeholder="Nama tim, anggota, NIM, topik, atau dosen" autocomplete="off" @input="search">
                </div>
            </div>
            @include('admin.plotting._filter-menu', ['panelId' => 'plot-tim-filter', 'groups' => [
                'specializations' => ['Peminatan', $teamSpecs->flatten()->unique()->sort()->mapWithKeys(fn ($s) => [$s => $s])->all()],
                'source' => ['Topik', ['mandiri' => 'Topik Mandiri', 'dosen' => 'Topik Dosen']],
                'missing' => ['Dosen', ['pembimbing' => 'Belum ada dosen pembimbing', 'penguji' => 'Belum ada dosen penguji']],
            ]])
        </div>
        <x-scroll-pager>
            <table class="data-table w-full min-w-[70rem] table-fixed border-t border-rule {{ $cells }} [&_tbody_tr:hover_td]:bg-transparent">
                <colgroup><col class="w-[11%]"><col class="w-[14%]"><col class="w-[14%]"><col class="w-[14%]"><col class="w-[13%]"><col class="w-[17%]"><col class="w-[17%]"></colgroup>
                <thead class="{{ $greenHead }}"><tr><th>Nama tim</th><th>Anggota</th><th>Peminatan</th><th>Topik Final</th><th>Status topik</th><th>Dosen Pembimbing</th><th>Dosen Penguji</th></tr></thead>
                <tbody>
                    @foreach ($teams as $team)
                        @php
                            $specializations = $teamSpecs[$team->id];
                            $searchText = mb_strtolower(collect([$team->name, $team->finalTopic->title, $team->supervisor?->lecturer->name, $team->examiner?->lecturer->name])
                                ->merge($team->members->pluck('user.name'))->merge($team->members->pluck('user.nim'))->filter()->implode(' '));
                            $missing = collect(['pembimbing' => ! $team->supervisor, 'penguji' => ! $team->examiner])->filter()->keys()->implode('|');
                        @endphp
                        <tr data-row data-search="{{ $searchText }}" data-specializations="{{ implode('|', $specializations) }}"
                            data-source="{{ $team->finalTopic->source }}" data-missing="{{ $missing }}" class="[&>td]:py-4 [&>td]:align-top">
                            <td class="font-semibold text-ink">{{ $team->name }}</td>
                            <td class="text-sm">
                                <ul class="divide-y divide-rule text-ink [&>li]:py-1.5 [&>li:first-child]:pt-0 [&>li:last-child]:pb-0">
                                    @foreach ($team->members->where('invite_status', 'diterima') as $m)
                                        <li>{{ $m->user->name }} @if ($m->is_leader)<span class="{{ $leaderChip }}">Ketua</span>@endif</li>
                                    @endforeach
                                </ul>
                            </td>
                            <td>
                                <div class="flex flex-wrap gap-1">
                                    @forelse ($specializations as $s)<span class="{{ $chip }}">{{ $s }}</span>@empty<span class="text-sm text-muted">Belum diisi</span>@endforelse
                                </div>
                            </td>
                            <td class="text-sm text-ink">{{ $team->finalTopic->title }}</td>
                            <td><span class="{{ $chip }} inline-block whitespace-nowrap">{{ $team->finalTopic->source === 'dosen' ? 'Topik Dosen' : 'Topik Mandiri' }}</span></td>
                            @foreach (['pembimbing', 'penguji'] as $role)
                                @php
                                    $assignment = $holder($team, $role);
                                    $other = $role === 'pembimbing' ? 'penguji' : 'pembimbing';
                                    $otherId = $holder($team, $other)?->lecturer_id;
                                    $reopen = session('plot_team') === "{$team->id}-{$role}";
                                    // Dosen sesuai peminatan tim (semua dosen bila anggota belum mengisi peminatan)
                                    $options = $specializations === [] ? $rows
                                        : $rows->filter(fn ($r) => in_array($r['lecturer']->lecturerProfile?->specialization, $specializations, true));
                                @endphp
                                <td x-data="registrationForm" data-open="{{ $reopen ? 'true' : 'false' }}">
                                    @if ($assignment)
                                        <p class="text-sm font-medium text-ink">{{ $assignment->lecturer->name }}</p>
                                        @if ($assignment->source === 'sistem')<p class="text-caption text-muted">Otomatis dari topik dosen</p>@endif
                                        <div class="mt-2 flex flex-wrap gap-1">
                                            <button type="button" class="btn btn-ghost btn-sm" @click="open" :aria-expanded="expanded"><x-heroicon-o-pencil-square class="h-4 w-4" /> Edit</button>
                                            <form method="POST" action="{{ route('admin.plotting.destroy', $assignment) }}" data-confirm="Hapus {{ $role }} {{ $assignment->lecturer->name }} dari {{ $team->name }}?" data-confirm-label="Hapus">
                                                @csrf @method('DELETE')<button class="btn btn-ghost btn-sm text-bad-ink"><x-heroicon-o-trash class="h-4 w-4" /> Hapus</button>
                                            </form>
                                        </div>
                                    @else
                                        <button type="button" class="btn btn-secondary btn-sm" @click="open" :aria-expanded="expanded"><x-heroicon-o-user-plus class="h-4 w-4" /> Plot dosen</button>
                                    @endif

                                    <x-modal width="sm:max-w-lg">
                                        <x-slot:heading>{{ $assignment ? 'Ganti' : 'Plot' }} {{ $role }} · {{ $team->name }}</x-slot:heading>
                                        <form method="POST" action="{{ route('admin.plotting.team', $team) }}" class="space-y-4">
                                            @csrf
                                            <input type="hidden" name="role" value="{{ $role }}">
                                            <p class="text-sm text-muted">
                                                @if ($specializations === [])
                                                    Anggota tim belum mengisi peminatan, jadi semua dosen ditampilkan.
                                                @else
                                                    Dosen dengan peminatan tim: {{ implode(', ', $specializations) }}.
                                                @endif
                                            </p>
                                            @php
                                                $chosenId = $reopen ? (int) old('lecturer_id') : $assignment?->lecturer_id;
                                                $chosen = $chosenId ? $options->first(fn ($r) => $r['lecturer']->id === $chosenId) : null;
                                            @endphp
                                            {{-- Pemilih dosen dengan pencarian nama (Alpine "plotLecturerPicker"); Simpan aktif setelah dosen dipilih --}}
                                            <div x-data="plotLecturerPicker" data-selected="{{ $chosen ? $chosenId : '' }}" data-selected-name="{{ $chosen ? $chosen['lecturer']->name : '' }}" class="space-y-4">
                                                @if ($options->isEmpty())
                                                    <p class="rounded-lg border border-rule bg-sunken px-3 py-2 text-sm text-muted">Belum ada dosen aktif dengan peminatan ini. Atur peminatan dosen di menu Dosen.</p>
                                                @else
                                                    <div @click.outside="hide" @keydown.escape.stop="hide">
                                                        <p class="field-label" id="plot-label-{{ $team->id }}-{{ $role }}">Dosen {{ $role }}<span class="text-bad-ink" aria-hidden="true"> *</span></p>
                                                        <input type="hidden" name="lecturer_id" :value="selected">
                                                        <button type="button" class="input flex items-center justify-between gap-2 border px-3 text-left" @click="toggle" :aria-expanded="open"
                                                                aria-labelledby="plot-label-{{ $team->id }}-{{ $role }}" aria-controls="plot-list-{{ $team->id }}-{{ $role }}">
                                                            <span class="truncate" :class="nothingChosen ? 'text-faint' : 'text-ink'" x-text="buttonLabel">{{ $chosen ? $chosen['lecturer']->name : 'Pilih dosen' }}</span>
                                                            <x-heroicon-o-chevron-down class="h-4 w-4 shrink-0 text-muted" />
                                                        </button>
                                                        <div x-show="open" x-cloak class="mt-2 rounded-lg border border-rule bg-surface">
                                                            <div class="relative border-b border-rule p-2">
                                                                <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-5 top-1/2 h-4 w-4 -translate-y-1/2 text-faint" />
                                                                <input x-ref="search" type="search" class="input pl-9" placeholder="Cari nama dosen" autocomplete="off" @input="search" aria-label="Cari nama dosen">
                                                            </div>
                                                            <ul id="plot-list-{{ $team->id }}-{{ $role }}" role="listbox" class="max-h-72 divide-y divide-rule overflow-y-auto">
                                                                @foreach ($options as $r)
                                                                    @php
                                                                        $id = $r['lecturer']->id;
                                                                        $quota = $r['quota'][$role];
                                                                        // Dosen yang sedang memegang peran ini dihitung ulang tanpa tim ini
                                                                        $count = $r['counts'][$role] - ($assignment?->lecturer_id === $id ? 1 : 0);
                                                                        $blocked = $otherId === $id;
                                                                    @endphp
                                                                    <li role="option" data-option data-search="{{ mb_strtolower($r['lecturer']->name) }}" :aria-selected="isChosen({{ $id }})">
                                                                        <button type="button" class="flex w-full items-start justify-between gap-3 px-3 py-2.5 text-left {{ $blocked ? 'cursor-not-allowed opacity-60' : 'hover:bg-sunken' }}"
                                                                                :class="isChosen({{ $id }}) ? 'bg-brand-tint' : ''" data-id="{{ $id }}" data-name="{{ $r['lecturer']->name }}" @click="choose" @disabled($blocked)>
                                                                            <span class="min-w-0">
                                                                                <span class="block text-sm font-medium text-ink">{{ $r['lecturer']->name }}</span>
                                                                                <span class="mt-1 flex flex-wrap items-center gap-1.5">
                                                                                    <span class="{{ $chip }}">{{ $r['lecturer']->lecturerProfile?->specialization ?? 'Tanpa peminatan' }}</span>
                                                                                    <x-flag :value="$balance->flag($count, $quota)" />
                                                                                </span>
                                                                                @if ($blocked)<span class="mt-1 block text-caption text-muted">Sudah menjadi {{ $other }} tim ini</span>@endif
                                                                            </span>
                                                                            <span class="shrink-0 text-right text-caption text-muted">
                                                                                @if ($quota['max'] === 0)
                                                                                    Tidak menerima tim
                                                                                @else
                                                                                    Sisa kuota <span class="block tabular text-sm font-semibold {{ $balance->isAvailable($count, $quota) ? 'text-ink' : 'text-bad-ink' }}">{{ $balance->remaining($count, $quota) }} / {{ $quota['max'] }}</span>
                                                                                @endif
                                                                            </span>
                                                                        </button>
                                                                    </li>
                                                                @endforeach
                                                            </ul>
                                                            <p x-show="noResults" x-cloak class="px-3 py-3 text-sm text-muted">Dosen tidak ditemukan.</p>
                                                        </div>
                                                        @if ($reopen)@error('lecturer_id')<p class="field-error">{{ $message }}</p>@enderror @endif
                                                    </div>
                                                @endif
                                                <div class="flex justify-end gap-3 border-t border-rule pt-4">
                                                    <button type="button" class="btn btn-ghost" @click="close">Batal</button>
                                                    <button class="btn btn-primary disabled:cursor-not-allowed disabled:opacity-50" data-once :disabled="nothingChosen" @disabled($options->isEmpty() || ! $chosen)>Simpan</button>
                                                </div>
                                            </div>
                                        </form>
                                    </x-modal>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-scroll-pager>
        <p x-show="empty" x-cloak class="border-t border-rule px-5 py-5 text-sm text-muted sm:px-6">Tidak ada tim yang cocok dengan pencarian atau filter.</p>
    @endif
</section>
