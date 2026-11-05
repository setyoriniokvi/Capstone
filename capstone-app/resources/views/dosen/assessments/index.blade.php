{{-- Penilaian dosen: kolom cari nama tim (langsung di browser, Alpine "tableSearch": satu baris ber-data-search per tim),
     di bawahnya tab per bentuk penilaian yang menjadi wewenang dosen (angka = tim yang belum lengkap dinilai; ?komponen=).
     Kartu tab aktif: judul bentuk penilaian + jendela penilaian, lalu daftar tim — Tim · Anggota · Jenis topik · Peran · Status · Nilai.
     Kartu menu utama: tabel pas selebar kartu — kolom persen, judul putih bergaris bawah, teks membungkus — tanpa scroll samping. --}}
@php
    $jenisLabel = ['mandiri' => 'Topik Mandiri', 'dosen' => 'Topik Dosen'];
    $label = fn (string $component) => config("capstone.components.{$component}.label");
@endphp
<x-layouts.app title="Penilaian">
    <x-page-head title="Penilaian berbasis rubrik" lede="Sebagai pembimbing: proposal, laporan progress, serta laporan & presentasi akhir. Sebagai penguji: laporan & presentasi akhir serta pameran." />

    @if ($components->isEmpty())
        <section class="panel"><x-empty title="Belum ada tim untuk dinilai" icon="pencil-square">Tim muncul setelah admin melakukan plotting.</x-empty></section>
    @else
    <div x-data="tableSearch">
        <form method="GET" action="{{ route('dosen.assessments.index') }}" @submit.prevent="apply" class="mb-4" role="search">
            <input type="hidden" name="komponen" value="{{ $active }}">
            <div class="relative">
                <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-muted" />
                <label for="cari-penilaian" class="sr-only">Cari nama tim</label>
                <input id="cari-penilaian" x-ref="query" type="search" name="q" value="{{ request('q') }}" @input="search" class="input min-h-[48px] rounded-xl pl-12" placeholder="Cari nama tim" autocomplete="off">
            </div>
        </form>

        {{-- Tab bentuk penilaian: sama lebar, memenuhi kartu --}}
        <section class="panel mb-6 p-2" aria-label="Bentuk penilaian">
            <nav class="flex flex-wrap gap-1">
                @foreach ($components as $component)
                    <a href="{{ route('dosen.assessments.index', ['komponen' => $component]) }}" @if ($active === $component) aria-current="page" @endif
                       class="flex min-h-[44px] flex-1 items-center justify-center gap-2 rounded-lg px-4 text-center text-sm font-medium {{ $active === $component ? 'bg-brand-tint text-brand-deep' : 'text-muted hover:bg-sunken hover:text-ink' }}">
                        {{ $label($component) }}
                        @if ($pendingCounts[$component] ?? 0)<span class="rounded-full bg-bad-ink px-2 text-caption font-semibold leading-5 text-white tabular" title="{{ $pendingCounts[$component] }} tim belum lengkap dinilai">{{ $pendingCounts[$component] }}</span>@endif
                    </a>
                @endforeach
            </nav>
        </section>

        <section class="panel overflow-hidden" aria-labelledby="judul-komponen">
            <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 px-5 py-4 sm:px-6">
                <h2 id="judul-komponen" class="text-lg font-semibold text-ink">{{ $label($active) }}</h2>
                @if ($window)
                    <p class="text-sm {{ $window->isOpen() ? 'text-ok-ink' : 'text-muted' }}">
                        {{ $window->isOpen() ? 'Dibuka' : (now()->lt($window->opens_at) ? 'Belum dibuka' : 'Ditutup') }} · {{ $window->opens_at->translatedFormat('j M') }} – {{ $window->due_at->translatedFormat('j M Y, H:i') }}
                    </p>
                @else
                    <p class="text-sm text-muted">Jendela penilaian belum diatur</p>
                @endif
            </div>
            <table class="data-table min-w-0 w-full table-fixed border-t border-rule [&_tbody_tr:hover_td]:bg-transparent [&_td]:break-words [&_th]:whitespace-normal">
                <colgroup><col class="w-[18%]"><col class="w-[25%]"><col class="w-[14%]"><col class="w-[13%]"><col class="w-[16%]"><col class="w-[14%]"></colgroup>
                <thead>
                    <tr class="[&>th]:border-b [&>th]:border-rule [&>th]:py-4 [&>th]:text-base [&>th]:font-semibold [&>th]:text-ink">
                        <th>Tim</th><th>Anggota</th><th>Jenis Topik</th><th>Peran</th><th>Status</th><th><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        @php
                            $team = $row['team'];
                            $isDraft = $drafts->has($team->id.':'.$active);
                        @endphp
                        <tr data-search="{{ mb_strtolower($team->name) }}" class="[&>td]:py-4 [&>td]:align-top">
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
                            <td>
                                @if ($team->finalTopic)
                                    <span class="rounded border border-rule bg-sunken px-1.5 py-0.5 text-caption font-medium text-muted">{{ $jenisLabel[$team->finalTopic->source] ?? ucfirst($team->finalTopic->source) }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="capitalize text-ink">{{ $row['role'] }}</td>
                            <td>@if ($row['done'])<x-badge class="!whitespace-normal" status="final" label="Lengkap" />@elseif ($isDraft)<x-badge class="!whitespace-normal" status="draft" label="Sebagian dinilai" />@else<x-badge class="!whitespace-normal" status="belum" label="Belum dinilai" />@endif</td>
                            <td class="text-center"><a href="{{ route('dosen.assessments.edit', [$team, $active]) }}" class="btn btn-secondary btn-sm w-full max-w-[8rem]">Nilai<span class="sr-only"> {{ $label($active) }} {{ $team->name }}</span></a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div x-show="empty" x-cloak>
                <x-empty title="Tidak ada tim yang cocok" icon="pencil-square">Ubah kata kunci pencarian.</x-empty>
            </div>
        </section>
    </div>
    @endif
</x-layouts.app>
