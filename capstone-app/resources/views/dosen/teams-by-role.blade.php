{{-- Menu "Tim Bimbingan" (pembimbing) dan "Tim Uji" (penguji): cari + filter jenis topik (Semua / Topik Mandiri / Topik Dosen),
     lalu tabel tim: nama tim, anggota (ketua ditandai), topik final, jenis topik, dan tombol "Lihat detail".
     "Lihat detail" membuka overlay detail tim (dosen/_team-detail); ?tim={id} membukanya saat halaman dimuat.
     Butuh $role, $title, $teams, $q, $jenis, $openTeam, $docMilestones, $minLogbooks (Lecturer\TeamController@byRole). --}}
@php
    $routeName = $role === 'pembimbing' ? 'dosen.teams.supervised' : 'dosen.teams.examined';
    $jenisLabel = ['mandiri' => 'Topik Mandiri', 'dosen' => 'Topik Dosen'];
@endphp
<x-layouts.app :title="$title">
    <x-page-head :title="$title" :lede="$role === 'pembimbing' ? 'Tim yang Anda bimbing pada periode aktif.' : 'Tim yang Anda uji pada periode aktif.'" />

    {{-- Pencarian + filter jenis topik (dropdown di samping kolom cari) — langsung di browser (Alpine "listFilter") --}}
    <div x-data="listFilter">
    <form method="GET" action="{{ route($routeName) }}" @submit.prevent="apply" class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center" role="search">
        <div class="flex gap-2 w-full sm:flex-1">
            <div class="relative min-w-0 flex-1">
                <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-muted" />
                <label for="cari-tim" class="sr-only">Cari tim</label>
                <input id="cari-tim" type="search" name="q" value="{{ $q }}" class="input min-h-[48px] rounded-xl pl-12" placeholder="Cari nama tim, anggota, NIM, atau topik" autocomplete="off" data-filter-search @input="search">
            </div>
            <x-sort-by tall target="#daftar-tim" id="urut-tim-dosen" default="tim" :options="['tim' => 'Tim', 'terbaru' => 'Tanggal', 'kategori' => 'Kategori (Jenis topik)']" />
        </div>
        <label for="jenis" class="sr-only">Filter jenis topik</label>
        <select id="jenis" name="jenis" data-filter="jenis" class="input min-h-[48px] w-full rounded-xl sm:w-60 sm:shrink-0" @change="filter">
            <option value="">Semua</option>
            @foreach ($jenisLabel as $value => $label)<option value="{{ $value }}" @selected($jenis === $value)>{{ $label }}</option>@endforeach
        </select>
        <noscript><button class="btn btn-secondary">Terapkan</button></noscript>
    </form>

    <section id="daftar-tim" data-sort-scope class="panel overflow-hidden" aria-label="Daftar {{ strtolower($title) }}">
        @if ($teams->isEmpty())
            <x-empty :title="'Belum ada '.strtolower($title)" icon="user-group" />
        @else
            {{-- Kartu menu utama: tabel pas selebar kartu (table-fixed, teks membungkus), tanpa scroll samping. --}}
            <table class="data-table min-w-0 w-full table-fixed [&_tbody_tr:hover_td]:bg-transparent [&_td]:break-words [&_th]:whitespace-normal">
                    <colgroup>
                        <col class="w-[16%]"><col class="w-[26%]"><col class="w-[30%]"><col class="w-[12%]"><col class="w-[16%]">
                    </colgroup>
                    <thead>
                        <tr class="[&>th]:border-b [&>th]:border-rule [&>th]:py-4 [&>th]:text-base [&>th]:font-semibold [&>th]:text-ink">
                            <th>Tim</th><th>Anggota</th><th>Topik</th><th>Jenis Topik</th><th><span class="sr-only">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody data-sort-list>
                        @foreach ($teams as $team)
                            @php $teamSearch = mb_strtolower(implode(' ', [$team->name, $team->finalTopic?->title, $team->members->map(fn ($m) => $m->user->name.' '.$m->user->nim)->implode(' ')])); @endphp
                            <tr x-data="registrationForm" data-open="{{ $openTeam === $team->id ? 'true' : 'false' }}" data-reveal="true"
                                data-filter-item data-search="{{ $teamSearch }}" data-filter-jenis="{{ $team->finalTopic?->source }}"
                                data-sort-item data-sort-tim="{{ $team->name }}" data-sort-terbaru="{{ $team->created_at?->timestamp }}" data-sort-kategori="{{ $team->finalTopic ? ($jenisLabel[$team->finalTopic->source] ?? $team->finalTopic->source) : '' }}" class="[&>td]:py-4 [&>td]:align-top">
                                <td class="font-semibold text-ink">{{ $team->name }}</td>
                                <td>
                                    <ul class="space-y-1">
                                        @foreach ($team->members as $member)
                                            <li class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-sm">
                                                <span class="text-ink">{{ $member->user->name }}</span>
                                                @if ($member->is_leader)<span class="rounded-md bg-brand-tint px-1.5 py-0.5 text-caption font-medium text-brand-deep inline-block whitespace-nowrap">Ketua</span>@endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </td>
                                <td class="{{ $team->finalTopic ? 'text-ink' : 'text-muted' }}">{{ $team->finalTopic?->title ?? 'Belum ditetapkan' }}</td>
                                <td>
                                    @if ($team->finalTopic)
                                        <span class="inline-block whitespace-nowrap rounded border border-rule bg-sunken px-1.5 py-0.5 text-caption font-medium text-muted">{{ $jenisLabel[$team->finalTopic->source] ?? ucfirst($team->finalTopic->source) }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="pl-4 text-right">
                                    {{-- Dua baris ("Lihat" / "detail") agar tombol tidak mepet ke kolom jenis topik --}}
                                    <button type="button" class="btn btn-secondary btn-sm h-auto flex-col gap-0 whitespace-normal py-1.5 leading-tight" @click="open" :aria-expanded="expanded"><span>Lihat</span><span>detail</span><span class="sr-only"> {{ $team->name }}</span></button>
                                    @include('dosen._team-detail')
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            <p x-show="empty" x-cloak class="border-t border-rule px-5 py-5 text-sm text-muted sm:px-6">Tidak ada tim yang cocok dengan pencarian atau filter.</p>
        @endif
    </section>
    </div>
</x-layouts.app>
