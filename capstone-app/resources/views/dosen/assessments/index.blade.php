{{-- Penilaian dosen: kolom cari + dropdown tim (Semua / Tim bimbingan / Tim uji) selebar halaman, menyaring baris
     langsung di browser saat mengetik/memilih (Alpine "tableSearch", baris ber-data-search & data-category). --}}
@php $categories = ['bimbingan' => 'Tim bimbingan', 'uji' => 'Tim uji']; @endphp
<x-layouts.app title="Penilaian">
    <x-page-head title="Penilaian berbasis rubrik" lede="Sebagai pembimbing: proposal, laporan progress, serta laporan & presentasi akhir. Sebagai penguji: laporan & presentasi akhir serta pameran." />

    <div x-data="tableSearch">
        <form method="GET" action="{{ route('dosen.assessments.index') }}" @submit.prevent="apply" class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center" role="search">
            <div class="relative w-full min-w-0 sm:flex-1">
                <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-muted" />
                <label for="cari-penilaian" class="sr-only">Cari penilaian</label>
                <input id="cari-penilaian" x-ref="query" type="search" name="q" value="{{ request('q') }}" @input="search" class="input min-h-[48px] rounded-xl pl-12" placeholder="Cari nama tim atau komponen penilaian" autocomplete="off">
            </div>
            <label for="kategori" class="sr-only">Filter tim</label>
            <select id="kategori" x-ref="category" name="kategori" @change="pick" class="input min-h-[48px] w-full rounded-xl sm:w-60 sm:shrink-0">
                <option value="">Semua</option>
                @foreach ($categories as $k => $l)<option value="{{ $k }}" @selected((string) $category === $k)>{{ $l }}</option>@endforeach
            </select>
        </form>

        <div class="panel">
            @if ($rows->isEmpty())
                <x-empty title="Belum ada tim untuk dinilai" icon="pencil-square">Tim muncul setelah admin melakukan plotting.</x-empty>
            @else
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Tim</th><th>Komponen</th><th>Peran</th><th>Jendela penilaian</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($rows as $row)
                                @php
                                    $w = $windows->get($row['component']);
                                    $isDraft = $drafts->has($row['team']->id.':'.$row['component']);
                                    $label = config('capstone.components.'.$row['component'].'.label');
                                @endphp
                                <tr data-search="{{ mb_strtolower($row['team']->name.' '.$label) }}" data-category="{{ $row['role'] === 'pembimbing' ? 'bimbingan' : 'uji' }}">
                                    <td class="font-medium">{{ $row['team']->name }}</td>
                                    <td>{{ $label }}</td>
                                    <td class="capitalize text-muted">{{ $row['role'] }}</td>
                                    <td class="whitespace-nowrap text-sm">
                                        @if ($w)
                                            {{ $w->opens_at->translatedFormat('j M') }} – {{ $w->due_at->translatedFormat('j M H:i') }}
                                            <p class="text-caption {{ $w->isOpen() ? 'text-ok-ink' : 'text-muted' }}">{{ $w->isOpen() ? 'dibuka' : (now()->lt($w->opens_at) ? 'belum dibuka' : 'ditutup') }}</p>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>@if ($row['done'])<x-badge status="final" label="Final" />@elseif ($isDraft)<x-badge status="draft" label="Draf" />@else<x-badge status="belum" label="Belum dinilai" />@endif</td>
                                    <td class="text-right"><a href="{{ route('dosen.assessments.edit', [$row['team'], $row['component']]) }}" class="btn btn-sm {{ $row['done'] ? 'btn-ghost' : 'btn-secondary' }}">{{ $row['done'] ? 'Lihat / ubah' : 'Nilai' }}</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div x-show="empty" x-cloak>
                    <x-empty title="Tidak ada penilaian yang cocok" icon="pencil-square">Ubah kata kunci atau pilihan tim.</x-empty>
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
