{{-- Tab Approval Topik Mandiri: kartu menunggu approval (pilih semua + Setujui pilihan; Tolak/Terima per topik) lalu,
     dipisah garis, riwayat keputusan 3 per halaman. Butuh $pending, $decided, $greenHead, $cells. --}}
<section x-data="bulkApproval" class="panel overflow-hidden" aria-labelledby="mandiri-judul">
    <div class="flex items-center justify-between gap-3 px-5 py-4 sm:px-6">
        <h2 id="mandiri-judul" class="text-lg font-semibold text-ink">Menunggu Approval</h2>
        <span class="text-sm text-muted">Menunggu keputusan: <span class="tabular font-medium text-ink">{{ $pending->count() }}</span></span>
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
                <tbody>
                    @foreach ($pending as $topic)
                        <tr x-data="disclosure" data-team="" class="[&>td]:py-4 [&>td]:align-top">
                            <td><input type="checkbox" class="checkbox" form="setujui-topik" name="topics[]" value="{{ $topic->id }}" data-select-row aria-label="Pilih {{ $topic->title }}"></td>
                            <td><p class="font-semibold text-ink">{{ $topic->team?->name }}</p></td>
                            <td>
                                <p class="font-semibold leading-snug text-ink">{{ $topic->title }}</p>
                                <p class="mt-1 whitespace-pre-line text-sm text-muted">{{ $topic->description }}</p>
                                @include('admin.topics._keyword-flags')
                            </td>
                            <td class="text-sm text-ink">{{ $topic->object ?: '—' }}</td>
                            <td class="!align-middle">@include('admin.topics._decision-actions')</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-scroll-pager>
    @endif

    {{-- Riwayat keputusan: 3 terbaru per halaman --}}
    <div class="border-t-2 border-rule">
        <h3 class="px-5 pb-2 pt-5 text-base font-semibold text-ink sm:px-6">Riwayat</h3>
        @if ($decided->isEmpty())
            <p class="px-5 pb-5 text-sm text-muted sm:px-6">Belum ada keputusan.</p>
        @else
            <x-scroll-pager>
                <table class="data-table w-full min-w-[64rem] table-fixed {{ $cells }} [&_tbody_tr:hover_td]:bg-transparent">
                    <colgroup><col class="w-[18%]"><col class="w-[34%]"><col class="w-[13%]"><col class="w-[15%]"><col class="w-[20%]"></colgroup>
                    <thead class="{{ $greenHead }}"><tr><th>Tim</th><th>Topik</th><th>Keputusan</th><th>Waktu</th><th>Catatan</th></tr></thead>
                    <tbody>
                        @foreach ($decided as $topic)
                            <tr class="[&>td]:align-top">
                                <td class="font-medium text-ink">{{ $topic->team?->name }}</td>
                                <td><p class="text-sm font-medium text-ink">{{ $topic->title }}</p>@if ($topic->object)<p class="text-caption text-muted">{{ $topic->object }}</p>@endif</td>
                                <td><x-badge class="!whitespace-normal" :status="$topic->status" /></td>
                                <td class="text-sm text-ink">{{ $topic->reviewed_at?->translatedFormat('j M Y, H:i') ?? '—' }}</td>
                                <td class="text-sm text-muted">{{ $topic->review_notes ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-scroll-pager>
            <x-history-pager :paginator="$decided" />
        @endif
    </div>
</section>
