{{-- Bank Topik Dosen (menu sidebar tersendiri di bawah Approval Topik): topik dosen yang sudah disetujui / ditambah admin.
     Admin dapat menambah (langsung terbit), mengubah, dan menghapus (termasuk topik yang sudah memiliki pelamar; tim
     terdampak diberi notifikasi) lewat formulir modal admin/topics/_lecturer-form (Alpine "topicForm").
     Butuh $bank, $period, serta $programs, $prodiSpecializations, $lecturers untuk formulir. --}}
@php
    $greenHead = 'bg-brand-tint [&>tr>th]:border-0 [&>tr>th]:py-3.5 [&>tr>th]:text-sm [&>tr>th]:font-semibold [&>tr>th]:text-brand-deep';
    $chip = 'rounded-md border border-rule bg-sunken px-2 py-0.5 text-caption font-medium text-muted';
    $proposerNames = fn ($topic) => $topic->proposers->isEmpty() ? collect([$topic->lecturer?->name]) : $topic->proposers->pluck('name');
    $formFields = ['title', 'description', 'competencies', 'prodi_codes', 'prodi_codes.*', 'specializations', 'specializations.*', 'object', 'quota', 'lecturer_id', 'proposer_ids', 'proposer_ids.*'];
@endphp
<x-layouts.app title="Bank Topik Dosen">
    <x-page-head title="Bank Topik Dosen" lede="Topik dosen yang sudah terbit dan dapat dilamar tim. Topik yang ditambahkan admin langsung terbit, topik usulan dosen masuk ke sini setelah disetujui di Approval Topik." />

    @error('topic')<p class="mb-6 rounded-lg border border-bad-line bg-bad-tint px-4 py-3 text-sm text-bad-ink">{{ $message }}</p>@enderror

<div x-data="topicForm" data-add-label="Tambah ke bank topik"
     data-open="{{ $errors->hasAny($formFields) ? 'true' : 'false' }}"
     data-store="{{ route('admin.lecturer-topics.store') }}"
     data-action="{{ old('_form_action', route('admin.lecturer-topics.store')) }}"
     data-editing="{{ old('_form_editing', '') }}">
    @if ($period)
        @include('admin.topics._lecturer-form')
    @endif

    <section data-sort-scope class="panel overflow-hidden" aria-labelledby="bank-judul">
        <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 sm:px-6">
            <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1">
                <h2 id="bank-judul" class="text-lg font-semibold text-ink">Bank Topik Dosen</h2>
                <span class="text-sm text-muted"><span class="tabular font-medium text-ink">{{ $bank->count() }}</span> topik terbit</span>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-3">
            @if ($bank->isNotEmpty())<x-sort-by id="urut-bank-topik" default="topik" :options="['topik' => 'Topik', 'nama' => 'Dosen pengusul', 'terbaru' => 'Tanggal', 'kategori' => 'Kategori (Peminatan)']" />@endif
            <button type="button" class="btn btn-primary btn-sm" @click="add" @disabled(! $period) @if (! $period) title="Buka dashboard periode terlebih dahulu" @endif>
                <x-heroicon-o-plus class="h-4 w-4" /> Tambah Topik
            </button>
            </div>
        </div>

        @if ($bank->isEmpty())
            <div class="border-t border-rule"><x-empty title="Belum ada topik dosen di bank topik" icon="book-open" /></div>
        @else
            <x-scroll-pager>
                <table class="data-table w-full min-w-[118rem] table-fixed border-t border-rule [&_td]:px-4 [&_td]:py-4 [&_th]:px-4 [&_tbody_tr:hover_td]:bg-transparent">
                    <colgroup>
                        <col class="w-[4rem]"><col class="w-[14rem]"><col class="w-[20rem]"><col class="w-[16rem]"><col class="w-[11rem]"><col class="w-[12rem]">
                        <col class="w-[11rem]"><col class="w-[7rem]"><col class="w-[13rem]"><col class="w-[7rem]"><col class="w-[9rem]">
                    </colgroup>
                    <thead class="{{ $greenHead }}"><tr>
                        <th>No</th><th>Topik</th><th>Deskripsi</th><th>Kompetensi yang diharapkan</th><th>Peminatan</th><th>Instansi / Objek</th>
                        <th>Program Studi</th><th class="text-center">Kuota</th><th>Dosen Pengusul</th><th class="text-center">Diterima</th><th class="text-center">Aksi</th>
                    </tr></thead>
                    <tbody data-sort-list>
                        @foreach ($bank as $topic)
                            @php
                                $hasApplicants = $topic->accepted_count > 0 || $topic->pending_count > 0;
                                $deleteNote = $hasApplicants ? " {$topic->accepted_count} tim diterima dan {$topic->pending_count} pengajuan di topik ini akan kehilangan topik dosennya dan diberi notifikasi." : '';
                            @endphp
                            <tr data-sort-item data-sort-topik="{{ $topic->title }}" data-sort-nama="{{ $proposerNames($topic)->filter()->implode(', ') }}"
                                data-sort-terbaru="{{ $topic->created_at?->timestamp }}" data-sort-kategori="{{ implode(', ', $topic->specializations ?? []) }}" class="align-top [&>td]:align-top">
                                <td class="tabular text-muted"><span data-sort-no>{{ $loop->iteration }}</span>.</td>
                                <td class="font-semibold leading-snug text-ink">{{ $topic->title }}</td>
                                <td class="whitespace-pre-line leading-relaxed text-muted">{{ $topic->description }}</td>
                                <td class="whitespace-pre-line leading-relaxed text-muted">{{ $topic->competencies ?: '—' }}</td>
                                <td>@forelse ($topic->specializations ?? [] as $spec)<span class="{{ $chip }} mb-1.5 mr-1 inline-block">{{ $spec }}</span>@empty<span class="text-muted">—</span>@endforelse</td>
                                <td class="text-ink">{{ $topic->object ?? '—' }}</td>
                                <td class="text-ink">@forelse ($topic->programNames() as $name)<p>{{ $name }}</p>@empty - @endforelse</td>
                                <td class="text-center tabular text-ink">{{ $topic->quota }}</td>
                                <td class="text-ink">@foreach ($proposerNames($topic) as $name)<p>{{ $name }}</p>@endforeach</td>
                                <td class="text-center tabular text-ink">{{ $topic->accepted_count }}</td>
                                <td class="text-center !align-middle">
                                    <div class="flex flex-col items-center gap-2">
                                        <button type="button" class="btn btn-secondary btn-sm w-full" @click="edit"
                                                data-topic="{{ json_encode(['action' => route('admin.lecturer-topics.update', $topic), 'title' => $topic->title, 'description' => $topic->description, 'competencies' => $topic->competencies, 'prodi' => $topic->prodi_codes ?? [], 'specializations' => $topic->specializations ?? [], 'object' => $topic->object, 'quota' => $topic->quota, 'lecturer' => $topic->lecturer_id, 'primary' => null, 'proposers' => $topic->proposers->pluck('id')->reject(fn ($id) => $id === $topic->lecturer_id)->values()]) }}">
                                            <x-heroicon-o-pencil-square class="h-4 w-4" /> Edit
                                        </button>
                                        <form method="POST" action="{{ route('admin.lecturer-topics.destroy', $topic) }}" data-confirm="Hapus topik “{{ $topic->title }}” dari bank topik dosen?{{ $deleteNote }}" data-confirm-label="Hapus" class="w-full">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-ghost btn-sm w-full text-bad-ink">
                                                <x-heroicon-o-trash class="h-4 w-4" /> Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-scroll-pager>
        @endif
    </section>
</div>
</x-layouts.app>
