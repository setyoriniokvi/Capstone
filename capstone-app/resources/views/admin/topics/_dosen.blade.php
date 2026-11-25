{{-- Tab Approval Topik Dosen: kartu menunggu approval (filter Semua / Flag / Non Flag di samping jumlah menunggu — menyaring
     baris langsung di browser lewat data-requirement; pilih semua + Setujui pilihan; Tolak/Terima per topik; dibagi per
     halaman di browser — x-client-pager). Topik yang
     disetujui masuk ke Bank Topik Dosen (menu tersendiri, admin/topics/bank). Butuh $pending, $greenHead, $cells. --}}
@php
    $proposerNames = fn ($topic) => $topic->proposers->isEmpty() ? collect([$topic->lecturer?->name]) : $topic->proposers->pluck('name');
@endphp
<section x-data="bulkApproval" data-page-size="10" data-sort-scope @sorted="apply" class="panel mb-6 overflow-hidden" aria-labelledby="dosen-judul">
    <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 sm:px-6">
        <h2 id="dosen-judul" class="text-lg font-semibold text-ink">Menunggu Approval</h2>
        <div class="flex flex-wrap items-center justify-end gap-x-4 gap-y-2">
            @if ($pending->isNotEmpty())
                <x-sort-by id="urut-menunggu-dosen" default="terbaru" dir="desc" :options="['terbaru' => 'Tanggal', 'topik' => 'Topik', 'nama' => 'Dosen pengusul', 'kategori' => 'Kategori (Flag)']" />
                {{-- Filter kata kunci terlarang: Semua / Flag (ditandai) / Non Flag --}}
                <label for="filter-flag-dosen" class="sr-only">Filter flag kata kunci terlarang</label>
                <select id="filter-flag-dosen" class="input min-h-[40px] w-auto py-1.5 text-sm" @change="filterRequirement">
                    <option value="">Semua</option>
                    <option value="flag">Flag</option>
                    <option value="nonflag">Non Flag</option>
                </select>
            @endif
            <span class="text-sm text-muted">Menunggu keputusan: <span class="tabular font-medium text-ink">{{ $pending->count() }}</span></span>
        </div>
    </div>

    @if ($pending->isEmpty())
        <p class="border-t border-rule px-5 py-5 text-sm text-muted sm:px-6">Tidak ada topik dosen yang menunggu.</p>
    @else
        @include('admin.topics._bulk-bar')
        <x-scroll-pager>
            <table class="data-table w-full min-w-[64rem] table-fixed border-t border-rule {{ $cells }} [&_tbody_tr:hover_td]:bg-transparent" @change="pick">
                <colgroup><col class="w-[5%]"><col class="w-[36%]"><col class="w-[17%]"><col class="w-[26%]"><col class="w-[16%]"></colgroup>
                <thead class="{{ $greenHead }}"><tr>
                    <th><input type="checkbox" class="checkbox" data-select-all :checked="allSelected" aria-label="Pilih semua topik yang menunggu"></th>
                    <th>Topik</th><th>Dosen pengusul</th><th>Rincian</th><th class="text-center">Aksi</th>
                </tr></thead>
                <tbody data-sort-list>
                    @foreach ($pending as $topic)
                        <tr x-data="disclosure" data-team="" data-sort-item data-sort-topik="{{ $topic->title }}" data-sort-nama="{{ $proposerNames($topic)->filter()->implode(', ') }}" data-sort-terbaru="{{ $topic->created_at?->timestamp }}" data-sort-kategori="{{ empty($topic->keyword_hits) ? 'Non Flag' : 'Flag' }}" data-requirement="{{ empty($topic->keyword_hits) ? 'nonflag' : 'flag' }}" class="[&>td]:py-4 [&>td]:align-top">
                            <td><input type="checkbox" class="checkbox" form="setujui-topik" name="topics[]" value="{{ $topic->id }}" data-select-row aria-label="Pilih {{ $topic->title }}"></td>
                            <td>
                                <p class="font-semibold leading-snug text-ink">{{ $topic->title }}</p>
                                <p class="mt-1 whitespace-pre-line text-sm text-muted">{{ $topic->description }}</p>
                                @if ($topic->competencies)<p class="mt-2 whitespace-pre-line text-sm"><span class="text-muted">Kompetensi:</span> {{ $topic->competencies }}</p>@endif
                                @include('admin.topics._keyword-flags')
                            </td>
                            <td class="text-sm text-ink">@foreach ($proposerNames($topic) as $name)<p>{{ $name }}</p>@endforeach</td>
                            <td class="text-sm">
                                <dl class="space-y-1.5">
                                    <div><dt class="text-caption text-muted">Peminatan</dt><dd class="text-ink">{{ implode(', ', $topic->specializations ?? []) ?: '—' }}</dd></div>
                                    <div><dt class="text-caption text-muted">Program studi</dt><dd class="text-ink">{{ implode(', ', $topic->programNames()) ?: '—' }}</dd></div>
                                    <div><dt class="text-caption text-muted">Objek</dt><dd class="text-ink">{{ $topic->object ?: '—' }}</dd></div>
                                    <div><dt class="text-caption text-muted">Kuota</dt><dd class="tabular text-ink">{{ $topic->quota }} tim</dd></div>
                                </dl>
                            </td>
                            <td class="!align-middle">@include('admin.topics._decision-actions')</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-scroll-pager>
        <p x-show="empty" x-cloak class="border-t border-rule px-5 py-5 text-sm text-muted sm:px-6">Tidak ada topik menunggu yang cocok dengan filter.</p>
        <x-client-pager id="per-halaman-dosen" label="Halaman topik dosen menunggu approval" />
    @endif
</section>
