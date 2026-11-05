{{-- F42 — Topik dosen: tabel seperti bank topik mahasiswa. Cari + dropdown peminatan + "Tambah Topik";
     baris hijau muda = topik yang diusulkan dosen yang login (pengusul utama maupun bersama dapat mengubah/menghapus),
     putih = topik dosen lain (lihat saja). --}}
@php
    $chip = 'rounded-md border border-rule bg-sunken px-2 py-0.5 text-caption font-medium text-muted';
    $phone = fn (?string $p) => $p ? preg_replace('/^(\d{4})(\d{4})(\d+)$/', '$1-$2-$3', $p) : null;
    $q = request('q');
    $peminatan = request('peminatan');
    $statusLabel = ['menunggu' => 'Menunggu persetujuan admin', 'ditolak' => 'Ditolak admin'];
@endphp
<x-layouts.app title="Topik Dosen">
    <x-page-head title="Topik Dosen" />

    {{-- Instruksi topik dosen dari admin --}}
    <x-topic-instruction :text="$instruction" />

    @error('topic')<p class="mb-6 rounded-lg border border-bad-line bg-bad-tint px-4 py-3 text-sm text-bad-ink">{{ $message }}</p>@enderror

    <div x-data="topicForm" data-open="{{ $formOpen || $errors->hasAny(['title', 'description', 'competencies', 'prodi_codes', 'prodi_codes.*', 'specializations', 'specializations.*', 'object', 'quota', 'proposer_ids', 'proposer_ids.*']) ? 'true' : 'false' }}"
         data-store="{{ route('dosen.topics.store') }}"
         data-action="{{ old('_form_action', $formTopic->exists ? route('dosen.topics.update', $formTopic) : route('dosen.topics.store')) }}"
         data-editing="{{ old('_form_editing', $formTopic->exists ? '1' : '') }}">
    @include('dosen.topics._form', ['topic' => $formTopic])

    <x-stat-row class="mb-6" :stats="[
        ['value' => $summary['topics'], 'label' => 'Topik Saya'],
        ['value' => $summary['awaiting'], 'label' => 'Menunggu Approval'],
        ['value' => $summary['applicants'], 'label' => 'Tim yang mengajukan', 'href' => route('dosen.applications.index'), 'linkLabel' => 'Buka Approval Pengajuan Topik'],
    ]" />

    {{-- Cari + filter peminatan + tambah topik --}}
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
        <form method="GET" action="{{ route('dosen.topics.index') }}" x-data="autoSubmit" class="flex min-w-0 flex-1 flex-col gap-3 sm:flex-row sm:items-center" role="search">
            <div class="relative w-full min-w-0 sm:flex-1">
                <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-muted" />
                <label for="cari-topik" class="sr-only">Cari topik</label>
                <input id="cari-topik" type="search" name="q" value="{{ $q }}" class="input min-h-[48px] rounded-xl pl-12" placeholder="Cari judul, dosen, atau objek">
            </div>
            <label for="peminatan" class="sr-only">Filter peminatan</label>
            <select id="peminatan" name="peminatan" class="input min-h-[48px] w-full rounded-xl sm:w-64 sm:shrink-0" @change="submit">
                <option value="">Semua peminatan</option>
                @foreach ($specializations as $spec)<option value="{{ $spec }}" @selected($peminatan === $spec)>{{ $spec }}</option>@endforeach
            </select>
            <noscript><button class="btn btn-secondary">Terapkan</button></noscript>
        </form>
        <button type="button" class="btn btn-primary min-h-[48px] shrink-0" @click="add" aria-controls="form-topik"><x-heroicon-o-plus class="h-5 w-5" /> Tambah Topik</button>
    </div>

    {{-- Keterangan warna baris --}}
    <ul class="mb-4 flex flex-wrap gap-x-6 gap-y-2 text-sm text-muted" aria-label="Keterangan warna baris">
        <li class="flex items-center gap-2"><span class="h-4 w-4 rounded-[3px] border border-rule-strong bg-surface" aria-hidden="true"></span>Topik dosen lain</li>
        <li class="flex items-center gap-2"><span class="h-4 w-4 rounded-[3px] border border-brand bg-brand-tint" aria-hidden="true"></span>Topik saya</li>
    </ul>


    <section class="panel overflow-hidden" aria-label="Daftar topik dosen">
        @if ($topics->isEmpty())
            <x-empty title="Tidak ada topik yang cocok" icon="book-open" />
        @else
            {{-- Kolom berlebar lega; bila melebihi layar tabel digeser ke samping. --}}
            <x-scroll-pager>
                <table class="data-table w-full min-w-[127rem] table-fixed [&_td]:px-4 [&_td]:py-4 [&_th]:px-4 [&_tbody_tr:hover_td]:bg-transparent">
                    <colgroup>
                        <col class="w-[4rem]"><col class="w-[14rem]"><col class="w-[20rem]"><col class="w-[16rem]"><col class="w-[11rem]"><col class="w-[12rem]">
                        <col class="w-[11rem]"><col class="w-[8rem]"><col class="w-[13rem]"><col class="w-[8rem]"><col class="w-[10rem]">
                    </colgroup>
                    <thead class="bg-brand-deep">
                        <tr class="[&>th]:border-0 [&>th]:py-3.5 [&>th]:text-center [&>th]:align-middle [&>th]:text-[0.6875rem] [&>th]:font-semibold [&>th]:uppercase [&>th]:leading-snug [&>th]:tracking-wide [&>th]:text-on-brand">
                            <th>No</th>
                            <th>Topik</th>
                            <th>Deskripsi</th>
                            <th>Kompetensi yang diharapkan</th>
                            <th>Peminatan</th>
                            <th>Instansi / Organisasi / Perusahaan Objek</th>
                            <th>Program Studi</th>
                            <th>Jumlah Tim yang Tersedia</th>
                            <th>Nama Dosen Pengusul</th>
                            <th>Jumlah Tim yang Diterima</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($topics as $topic)
                            @php
                                $own = $topic->isProposer(auth()->user());
                                $primary = $topic->lecturer_id === auth()->id();
                                $hasApplicants = $topic->accepted_count > 0 || $topic->pending_count > 0;
                            @endphp
                            <tr class="align-top [&>td]:align-top {{ $own ? 'bg-brand-tint' : '' }}">
                                <td class="tabular text-muted">{{ $loop->iteration }}.</td>
                                <td>
                                    <p class="font-semibold leading-snug text-ink">{{ $topic->title }}</p>
                                    @if ($own && ! $primary)<p class="mt-2 text-caption text-muted">Anda pengusul bersama</p>@endif
                                    @if ($own && $topic->status !== 'diterima')
                                        <x-badge class="mt-2" :status="$topic->status" :label="$statusLabel[$topic->status] ?? ucfirst($topic->status)" />
                                    @endif
                                    @if ($own && $topic->review_notes)<p class="mt-2 text-caption text-muted"><span class="font-medium text-ink">Catatan admin:</span> {{ $topic->review_notes }}</p>@endif
                                </td>
                                <td class="whitespace-pre-line leading-relaxed text-muted">{{ $topic->description }}</td>
                                <td class="whitespace-pre-line leading-relaxed text-muted">{{ $topic->competencies ?: '—' }}</td>
                                <td>
                                    @forelse ($topic->specializations ?? [] as $spec)<span class="{{ $chip }} mb-1.5 mr-1 inline-block">{{ $spec }}</span>@empty<span class="text-muted">—</span>@endforelse
                                </td>
                                <td class="text-ink">{{ $topic->object ?? '—' }}</td>
                                <td class="text-ink">@forelse ($topic->programNames() as $name)<p>{{ $name }}</p>@empty — @endforelse</td>
                                <td class="text-center tabular text-ink">{{ $topic->quota }}</td>
                                <td>
                                    {{-- Pengusul utama tercatat pertama --}}
                                    <ul class="space-y-2">
                                        @foreach ($topic->proposers->isEmpty() ? collect([$topic->lecturer]) : $topic->proposers as $proposer)
                                            <li>
                                                <p class="font-medium text-ink">{{ $proposer->name }}</p>
                                                @if ($proposer->phone)<p class="text-caption text-muted tabular">WA {{ $phone($proposer->phone) }}</p>@endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </td>
                                <td class="text-center tabular text-ink">{{ $topic->accepted_count }}</td>
                                <td class="text-center !align-middle">
                                    @if ($own)
                                        <div class="flex flex-col items-center gap-2">
                                            <button type="button" class="btn btn-secondary btn-sm w-full" @click="edit" aria-controls="form-topik"
                                                    data-topic="{{ json_encode(['action' => route('dosen.topics.update', $topic), 'title' => $topic->title, 'description' => $topic->description, 'competencies' => $topic->competencies, 'prodi' => $topic->prodi_codes ?? [], 'specializations' => $topic->specializations ?? [], 'object' => $topic->object, 'quota' => $topic->quota,
                                                        'primary' => $primary ? null : ['id' => $topic->lecturer_id, 'name' => $topic->lecturer->name],
                                                        'proposers' => $topic->proposers->pluck('id')->reject(fn ($id) => $id === $topic->lecturer_id || $id === auth()->id())->values()]) }}">
                                                <x-heroicon-o-pencil-square class="h-4 w-4" /> Edit
                                            </button>
                                            <form method="POST" action="{{ route('dosen.topics.destroy', $topic) }}" data-confirm="Hapus topik “{{ $topic->title }}”?" data-confirm-label="Hapus" class="w-full">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-ghost btn-sm w-full text-bad-ink disabled:cursor-not-allowed disabled:opacity-50" @disabled($hasApplicants) @if ($hasApplicants) title="Topik yang sudah memiliki pelamar tidak dapat dihapus" @endif>
                                                    <x-heroicon-o-trash class="h-4 w-4" /> Delete
                                                </button>
                                            </form>
                                        </div>
                                    @else
                                        <span class="text-muted" aria-label="Tidak ada aksi">—</span>
                                    @endif
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
