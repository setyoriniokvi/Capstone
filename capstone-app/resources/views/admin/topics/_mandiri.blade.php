{{-- Tab Approval Topik Mandiri: kartu menunggu approval (pilih semua + Setujui pilihan; Tolak/Terima per topik), dibagi per
     halaman di browser (x-client-pager). Butuh $pending, $greenHead, $cells. --}}
<section x-data="bulkApproval" data-page-size="10" data-sort-scope @sorted="apply" class="panel overflow-hidden" aria-labelledby="mandiri-judul">
    <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 sm:px-6">
        <h2 id="mandiri-judul" class="text-lg font-semibold text-ink">Menunggu Approval</h2>
        <div class="flex flex-wrap items-center justify-end gap-x-4 gap-y-2">
            @if ($pending->isNotEmpty())<x-sort-by id="urut-menunggu-mandiri" default="terbaru" dir="desc" :options="['terbaru' => 'Tanggal', 'nama' => 'Nama tim', 'topik' => 'Topik', 'kategori' => 'Kategori (Flag)']" />@endif
            <span class="text-sm text-muted">Menunggu keputusan: <span class="tabular font-medium text-ink">{{ $pending->count() }}</span></span>
        </div>
    </div>

    @if ($pending->isEmpty())
        <p class="border-t border-rule px-5 py-5 text-sm text-muted sm:px-6">Tidak ada topik mandiri yang menunggu.</p>
    @else
        @include('admin.topics._bulk-bar')
        <x-scroll-pager>
            <table class="data-table w-full min-w-[64rem] table-fixed border-t border-rule {{ $cells }} [&_tbody_tr:hover_td]:bg-transparent" @change="pick">
                <colgroup><col class="w-[5%]"><col class="w-[17%]"><col class="w-[40%]"><col class="w-[22%]"><col class="w-[16%]"></colgroup>
                <thead class="{{ $greenHead }}"><tr>
                    <th><input type="checkbox" class="checkbox" data-select-all :checked="allSelected" aria-label="Pilih semua topik yang menunggu"></th>
                    <th>Tim</th><th>Topik</th><th>Objek Capstone</th><th class="text-center">Aksi</th>
                </tr></thead>
                <tbody data-sort-list>
                    @foreach ($pending as $topic)
                        <tr x-data="disclosure" data-team="" data-sort-item data-sort-nama="{{ $topic->team?->name }}" data-sort-topik="{{ $topic->title }}" data-sort-terbaru="{{ $topic->created_at?->timestamp }}" data-sort-kategori="{{ empty($topic->keyword_hits) ? 'Non Flag' : 'Flag' }}" class="[&>td]:py-4 [&>td]:align-top">
                            <td><input type="checkbox" class="checkbox" form="setujui-topik" name="topics[]" value="{{ $topic->id }}" data-select-row aria-label="Pilih {{ $topic->title }}"></td>
                            <td><p class="font-semibold text-ink">{{ $topic->team?->name }}</p></td>
                            <td>
                                <p class="font-semibold leading-snug text-ink">{{ $topic->title }}</p>
                                <p class="mt-1 whitespace-pre-line text-sm text-muted">{{ $topic->description }}</p>
                                @include('admin.topics._keyword-flags')
                            </td>
                            <td class="text-sm text-ink">
                                {{ $topic->object ?: '—' }}
                                @if ($topic->object_detail)<p class="mt-1 whitespace-pre-line break-words text-caption text-muted">{!! \App\Support\Format::linkify($topic->object_detail) !!}</p>@endif
                            </td>
                            <td class="!align-middle">@include('admin.topics._decision-actions')</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-scroll-pager>
        <x-client-pager id="per-halaman-mandiri" label="Halaman topik mandiri menunggu approval" />
    @endif

</section>
