{{-- Tab "Topik Dosen" (F22): formulir pengajuan di atas (dibuka tombol "Ajukan") → cari + dropdown peminatan
     → tabel topik urut nama dosen. Butuh $team, $topics, $specializations, $canApply. --}}
@php
    $chip = 'rounded-md border border-rule bg-sunken px-2 py-0.5 text-caption font-medium text-muted';
    $phone = fn (?string $p) => $p ? preg_replace('/^(\d{4})(\d{4})(\d+)$/', '$1-$2-$3', $p) : null;
    $applications = $team?->applications ?? collect();
    $accepted = $applications->firstWhere('status', 'diterima');
    $q = request('q');
    $peminatan = request('peminatan');
@endphp
<div x-data="applyForm" data-open="{{ $errors->has('portfolio_url') ? 'true' : 'false' }}" data-action="{{ old('_topic_action') }}" data-title="{{ old('_topic_title') }}">
    @if ($canApply)
<x-modal>
    <x-slot:heading>Ajukan topik dosen</x-slot:heading>
            <form method="POST" :action="action" class="space-y-4">
                @csrf
                <input type="hidden" name="_topic_action" :value="action">
                <input type="hidden" name="_topic_title" :value="title">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="field-label" for="apply_team">Nama tim</label>
                        <input id="apply_team" class="input bg-sunken text-muted" value="{{ $team->name }}" readonly aria-readonly="true">
                    </div>
                    <div>
                        <label class="field-label" for="apply_topic">Topik</label>
                        <input id="apply_topic" class="input bg-sunken text-muted" :value="title" readonly aria-readonly="true">
                    </div>
                </div>
                <x-field label="Portofolio" name="portfolio_url" required hint="Tautan portofolio tim (Google Drive, OneDrive, Dropbox, atau lainnya). Pastikan aksesnya dapat dilihat oleh dosen.">
                    <input id="portfolio_url" x-ref="portfolio" type="text" name="portfolio_url" class="input" value="{{ old('portfolio_url') }}" placeholder="Tautan portofolio tim" required maxlength="500">
                </x-field>
                <div class="flex flex-wrap justify-end gap-3 border-t border-rule pt-4">
                    <button type="button" class="btn btn-ghost" @click="close">Batal</button>
                    <button class="btn btn-primary" data-once>Kirim pengajuan</button>
                </div>
            </form>
        </x-modal>
    @endif

    {{-- Status pengajuan tim --}}
    @if ($accepted)
        <p class="mb-6 flex items-center gap-3 rounded-lg border border-ok-line bg-ok-tint px-4 py-3 text-sm text-ok-ink"><x-heroicon-o-information-circle class="h-5 w-5 shrink-0" />Tim Anda sudah memiliki topik final. Pengajuan topik dosen dinonaktifkan.</p>
    @elseif (! $canApply)
        <p class="mb-6 flex items-center gap-3 rounded-lg border border-rule bg-sunken px-4 py-3 text-sm text-muted"><x-heroicon-o-information-circle class="h-5 w-5 shrink-0" />Pengajuan topik dosen dibuka setelah registrasi tim dan topik mandiri diterima.</p>
    @endif

    {{-- Pencarian + filter peminatan (dropdown di samping kolom cari) --}}
    <form method="GET" action="{{ route('mahasiswa.topic.index') }}" x-data="autoSubmit" class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center" role="search">
        <input type="hidden" name="tab" value="dosen">
        <div class="relative w-full min-w-0 sm:flex-1">
            <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-muted" />
            <label for="cari-topik" class="sr-only">Cari topik</label>
            <input id="cari-topik" type="search" name="q" value="{{ $q }}" class="input min-h-[48px] rounded-xl pl-12" placeholder="Cari judul, dosen, atau objek">
        </div>
        <label for="peminatan" class="sr-only">Filter peminatan</label>
        <select id="peminatan" name="peminatan" class="input min-h-[48px] w-full rounded-xl sm:w-72 sm:shrink-0" @change="submit">
            <option value="">Semua peminatan</option>
            @foreach ($specializations as $spec)<option value="{{ $spec }}" @selected($peminatan === $spec)>{{ $spec }}</option>@endforeach
        </select>
        <noscript><button class="btn btn-secondary">Terapkan</button></noscript>
    </form>

    {{-- Keterangan warna baris --}}
    <ul class="mb-4 flex flex-wrap gap-x-6 gap-y-2 text-sm text-muted" aria-label="Keterangan warna baris">
        <li class="flex items-center gap-2"><span class="h-4 w-4 rounded-[3px] border border-rule-strong bg-surface" aria-hidden="true"></span>Topik tersedia</li>
        <li class="flex items-center gap-2"><span class="h-4 w-4 rounded-[3px] border border-rule-strong bg-sunken" aria-hidden="true"></span>Kuota penuh</li>
        <li class="flex items-center gap-2"><span class="h-4 w-4 rounded-[3px] border border-brand bg-brand-tint" aria-hidden="true"></span>Topik yang saya ajukan</li>
    </ul>
    <p class="mb-6 text-caption text-muted">Tim boleh mengajukan lebih dari satu topik. Begitu satu topik diterima dosen, pengajuan lain yang masih menunggu otomatis dibatalkan.</p>

    {{-- Daftar topik dosen dalam satu tabel --}}
    <section class="panel overflow-hidden" aria-label="Daftar topik dosen">
        @if ($topics->isEmpty())
            <x-empty title="Tidak ada topik yang cocok" icon="book-open" />
        @else
            {{-- Kolom berlebar lega; bila melebihi layar tabel digeser ke samping
                 (wrapper sengaja bukan .table-wrap agar tidak diubah ke tampilan kartu). --}}
            <div class="overflow-x-auto">
                <table class="data-table w-full min-w-[111rem] table-fixed [&_td]:px-4 [&_td]:py-4 [&_th]:px-4 [&_tbody_tr:hover_td]:bg-transparent">
                    <colgroup>
                        <col class="w-[4rem]"><col class="w-[14rem]"><col class="w-[20rem]"><col class="w-[12rem]"><col class="w-[12rem]">
                        <col class="w-[11rem]"><col class="w-[8rem]"><col class="w-[12rem]"><col class="w-[8rem]"><col class="w-[10rem]">
                    </colgroup>
                    <thead class="bg-brand-deep">
                        <tr class="[&>th]:border-0 [&>th]:py-3.5 [&>th]:text-center [&>th]:align-middle [&>th]:text-[0.6875rem] [&>th]:font-semibold [&>th]:uppercase [&>th]:leading-snug [&>th]:tracking-wide [&>th]:text-on-brand">
                            <th>No</th>
                            <th>Topik</th>
                            <th>Deskripsi</th>
                            <th>Kompetensi yang diharapkan</th>
                            <th>Instansi / Organisasi / Perusahaan Objek</th>
                            <th>Program Studi</th>
                            <th>Jumlah Tim yang Tersedia</th>
                            <th>Nama Dosen Pengusul</th>
                            <th>Jumlah Tim yang Diterima</th>
                            <th>Status Pendaftaran</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($topics as $topic)
                            @php
                                $remaining = max(0, $topic->quota - $topic->accepted_count);
                                $mine = $applications->first(fn ($a) => $a->topic_id === $topic->id && $a->status !== 'dibatalkan');
                                $lecturer = $topic->lecturer;
                                // Hijau muda = diajukan tim, abu-abu = kuota penuh, putih = tersedia
                                $rowTone = $mine ? 'bg-brand-tint' : ($remaining === 0 ? 'bg-sunken' : '');
                            @endphp
                            <tr class="align-top [&>td]:align-top {{ $rowTone }}">
                                <td class="tabular text-muted">{{ $loop->iteration }}.</td>
                                <td class="font-semibold leading-snug text-ink">{{ $topic->title }}</td>
                                <td class="whitespace-pre-line leading-relaxed text-muted">{{ $topic->description }}</td>
                                <td>@if ($topic->competencies)<span class="{{ $chip }} inline-block">{{ $topic->competencies }}</span>@else<span class="text-muted">—</span>@endif</td>
                                <td class="text-ink">{{ $topic->object ?? '—' }}</td>
                                <td class="text-ink">{{ $topic->studyProgram?->name ?? $topic->prodi_code ?? '—' }}</td>
                                <td class="text-center tabular text-ink">{{ $topic->quota }}</td>
                                <td>
                                    <p class="font-medium text-ink">{{ $lecturer->name }}</p>
                                    @if ($lecturer->phone)<p class="text-caption text-muted tabular">WA {{ $phone($lecturer->phone) }}</p>@endif
                                </td>
                                <td class="text-center tabular text-ink">{{ $topic->accepted_count }}</td>
                                <td class="text-center !align-middle">
                                    @if ($mine)
                                        <x-badge :status="$mine->status" :label="ucfirst($mine->status)" />
                                        @if ($mine->status === 'menunggu')
                                            <form method="POST" action="{{ route('mahasiswa.bank.withdraw', $mine) }}" data-confirm="Batalkan pengajuan ke “{{ $topic->title }}”?" data-confirm-label="Batalkan pengajuan" class="mt-2">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-ghost btn-sm text-bad-ink">Batalkan</button>
                                            </form>
                                        @endif
                                    @else
                                        <button type="button" class="btn btn-secondary btn-sm disabled:cursor-not-allowed disabled:opacity-50" @click="pick"
                                                data-action="{{ route('mahasiswa.bank.apply', $topic) }}" data-title="{{ $topic->title }}"
                                                @disabled(! $canApply || $remaining === 0)>Ajukan<span class="sr-only"> topik {{ $topic->title }}</span></button>
                                        @if ($remaining === 0)<p class="mt-1.5 text-caption font-medium text-bad-ink">Kuota penuh</p>@endif
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
