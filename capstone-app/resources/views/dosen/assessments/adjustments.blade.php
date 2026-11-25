{{-- Penyesuaian Nilai (menu Penilaian dosen, khusus pembimbing). Kartu "Mahasiswa bimbingan": Cari | Sort (Nama / Kelas /
     Tim) langsung di browser (Alpine "listFilter" + <x-sort-by>), lalu tabel selebar kartu tanpa scroll samping:
     Mahasiswa (nama + NIM) | Kelas | Tim | Nilai final | Nilai disesuaikan (isian 0–100 + Simpan di kanannya, selalu
     sebaris). Layar sempit: teks membungkus.
     Nilai final = nilai disesuaikan bila diisi, selain itu nilai sistem; Simpan dengan isian kosong = kembali ke nilai sistem.
     Isian aktif setelah nilai sistem tim lengkap (selain itu tetap tampil tetapi mati). Nilai disesuaikan menggantikan nilai
     akhir & indeks di Rekap Penilaian; kolom iGadis diskalakan proporsional. Tidak terlihat mahasiswa.
     Butuh $results (ScoreCalculator::forTeam + team). --}}
@php
    $num = fn ($v) => number_format((float) $v, 2, ',', '');
    $count = $results->sum(fn ($r) => $r['rows']->count());
@endphp
<x-layouts.app title="Penyesuaian Nilai">
    <x-page-head title="Penyesuaian Nilai">
        <x-slot:actions>
            <a href="{{ route('dosen.assessments.index') }}" class="btn btn-ghost">Kembali</a>
        </x-slot:actions>
    </x-page-head>

    @error('score')<p class="mb-4 rounded-lg border border-bad-line bg-bad-tint px-4 py-3 text-sm text-bad-ink">{{ $message }}</p>@enderror

    <section x-data="listFilter" data-sort-scope class="panel mb-6 overflow-hidden" aria-labelledby="penyesuaian-judul">
        <div class="flex flex-wrap items-baseline justify-between gap-3 px-5 pt-4 sm:px-6">
            <h2 id="penyesuaian-judul" class="text-lg font-semibold text-ink">Mahasiswa bimbingan</h2>
            <p class="text-sm text-muted"><span class="tabular font-medium text-ink">{{ $count }}</span> mahasiswa</p>
        </div>
        {{-- Cari & Sort langsung di browser --}}
        <div class="flex gap-2 px-5 pb-4 pt-3 sm:px-6" role="search">
            <label for="cari-penyesuaian" class="sr-only">Cari mahasiswa</label>
            <input id="cari-penyesuaian" type="search" class="input min-w-0 flex-1" placeholder="Cari NIM, nama, kelas, tim" autocomplete="off"
                   data-filter-search @input="search" @disabled($count === 0)>
            <x-sort-by id="urut-penyesuaian" default="nama" :options="['nama' => 'Nama', 'kelas' => 'Kelas', 'tim' => 'Tim']" />
        </div>
        @if ($count === 0)
            <div class="border-t border-rule"><x-empty title="Belum ada mahasiswa bimbingan" icon="user-group">Daftar muncul setelah Anda ditetapkan sebagai pembimbing tim yang diterima.</x-empty></div>
        @else
            {{-- Tabel pas selebar kartu (table-fixed, teks membungkus), tanpa scroll samping --}}
            <table class="data-table w-full min-w-0 table-fixed border-t border-rule max-md:[&_td]:!px-2.5 max-md:[&_th]:!px-2.5 [&_td]:break-words [&_td]:align-middle [&_td]:text-sm [&_th]:whitespace-normal [&_tbody_tr:hover_td]:bg-transparent">
                <thead class="bg-brand-tint [&>tr>th]:border-0 [&>tr>th]:py-3 [&>tr>th]:text-sm [&>tr>th]:font-semibold [&>tr>th]:text-brand-deep">
                    <tr>
                        {{-- Nilai disesuaikan selebar isian + Simpan; kolom lain berbagi sisa lebar --}}
                        <th class="w-[34%]">Mahasiswa</th>
                        <th class="w-[16%]">Kelas</th>
                        <th class="w-[26%]">Tim</th>
                        <th class="w-[24%] !text-center">Nilai final</th>
                        <th class="w-[8.75rem] !text-center md:w-[10rem]">Nilai disesuaikan</th>
                    </tr>
                </thead>
                <tbody data-sort-list>
                    @foreach ($results as $result)
                        @foreach ($result['rows'] as $row)
                            @php
                                $student = $row['student'];
                                $team = $result['team'];
                                $form = 'penyesuaian-'.$team->id.'-'.$student->id;
                                $adjustment = $row['adjustment'];
                                $ready = $result['complete'];
                                $hint = $ready ? null : 'Nilai sistem belum lengkap: '.collect($result['missing'])->map(fn ($c) => config("capstone.components.$c.label"))->implode(', ');
                                $search = mb_strtolower(implode(' ', [$student->nim, $student->name, $student->class_name, $team->name]));
                            @endphp
                            <tr data-filter-item data-search="{{ $search }}" data-sort-item data-sort-nama="{{ $student->name }}" data-sort-kelas="{{ $student->class_name }}" data-sort-tim="{{ $team->name }}">
                                <td>
                                    <p class="font-medium text-ink">{{ $student->name }}</p>
                                    <p class="code text-caption text-muted">{{ $student->nim }}</p>
                                </td>
                                <td class="code">{{ $student->class_name ?? '-' }}</td>
                                <td>{{ $team->name }}</td>
                                <td class="text-center tabular">
                                    @if ($ready)
                                        <span class="font-semibold text-ink">{{ $num($row['total']) }}</span>
                                        @if ($row['adjusted'] !== null)<p class="text-caption text-muted">Sistem {{ $num($row['system_total']) }}</p>@endif
                                    @else
                                        <span class="text-muted" title="{{ $hint }}">-</span>
                                    @endif
                                </td>
                                <td>
                                    {{-- Isian + Simpan selalu sebaris (Simpan di kanan isian) --}}
                                    <form id="{{ $form }}" method="POST" action="{{ route('dosen.adjustments.update', [$team, $student]) }}" class="flex flex-nowrap items-center justify-center gap-1.5">
                                        @csrf @method('PUT')
                                        <label class="sr-only" for="{{ $form }}-score">Nilai disesuaikan {{ $student->name }}</label>
                                        <input id="{{ $form }}-score" name="score" type="number" step="0.01" min="0" max="100" class="input tabular w-14 shrink-0 px-1.5 text-center md:w-16"
                                               value="{{ $adjustment ? rtrim(rtrim(number_format($adjustment->score, 2, '.', ''), '0'), '.') : '' }}" placeholder="-" @disabled(! $ready) @if ($hint) title="{{ $hint }}" @endif>
                                        <button class="btn btn-primary btn-sm shrink-0 max-sm:!px-2 disabled:cursor-not-allowed disabled:opacity-50" data-once @disabled(! $ready)
                                                title="{{ $hint ?? 'Kosongkan nilai disesuaikan lalu Simpan untuk memakai nilai sistem' }}">Simpan</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
            <p x-show="empty" x-cloak class="border-t border-rule px-5 py-5 text-sm text-muted sm:px-6">Tidak ada mahasiswa yang cocok dengan pencarian.</p>
        @endif
    </section>
</x-layouts.app>
