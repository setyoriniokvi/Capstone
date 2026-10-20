{{-- F26 / F33–F41 — Dokumen milestone tim. Judul "Dokumen Tim", nama tim di bawahnya; dropdown rentang tenggat; satu kartu berisi
     milestone dokumen (dibuat admin) dikelompokkan per tanggal tenggat. Pengumpulan per tim: unggahan satu
     anggota menyelesaikan milestone untuk seluruh tim. Kanan: tombol unggah (nonaktif "Sudah Dikumpulkan" setelah
     terkumpul); di bawahnya, selebar kartu: info pengumpulan, Lihat file / Edit / Hapus, dan feedback dosen. --}}
@php
    $locked = fn ($m) => match (true) {
        $m->document_type === 'materi_presentasi' && $approvedLogbooks < $minLogbooks => "Terkunci — minimal {$minLogbooks} log bimbingan disetujui ({$approvedLogbooks}/{$minLogbooks}).",
        ! $m->hasStarted() => 'Dibuka '.$m->starts_at->translatedFormat('j M Y, H:i').'.',
        default => null,
    };
@endphp
<x-layouts.app title="Dokumen" section="Mahasiswa · Dokumen">
    <x-page-head title="Dokumen Tim" :lede="$team?->name" />

    @if ($gate)
        <div class="panel"><x-empty :title="$gate" icon="lock-closed" /></div>
    @else
        {{-- Filter rentang tenggat (dropdown) --}}
        <form method="GET" action="{{ route('mahasiswa.documents.index') }}" x-data="autoSubmit" class="mb-6">
            <label for="rentang" class="sr-only">Rentang tenggat</label>
            <select id="rentang" name="rentang" class="input min-h-[48px] w-full rounded-xl sm:w-64" @change="submit">
                @foreach (\App\Http\Controllers\Student\DocumentController::RANGES as $key => $label)
                    <option value="{{ $key }}" @selected($range === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <noscript><button class="btn btn-secondary mt-2">Terapkan</button></noscript>
        </form>

        <section class="panel divide-y divide-rule" aria-label="Pengumpulan dokumen milestone">
            @forelse ($groups as $date => $milestones)
                <div class="px-5 py-6 sm:px-8">
                    <h2 class="text-xl font-semibold tracking-tight text-ink">{{ \Illuminate\Support\Carbon::parse($date)->translatedFormat('l, j F Y') }}</h2>

                    <ul class="mt-2 divide-y divide-rule">
                        @foreach ($milestones as $m)
                            @php
                                $doc = $submissions->get($m->id);
                                $latest = $doc?->versions->first();
                                $reason = $locked($m);
                                $fileUrl = $doc?->driveUrl() ?? ($latest ? route('documents.download', $latest) : null);
                            @endphp
                            <li x-data="disclosure" class="grid gap-x-5 gap-y-4 py-5 sm:grid-cols-[2.5rem_minmax(0,1fr)_16rem]">
                                <span class="hidden h-10 w-10 place-items-center rounded-lg {{ $doc ? 'bg-ok-tint text-ok-ink' : 'bg-brand-wash text-brand' }} sm:grid" aria-hidden="true">
                                    @if ($doc)<x-heroicon-o-document-check class="h-6 w-6" />@else<x-heroicon-o-document-arrow-up class="h-6 w-6" />@endif
                                </span>

                                <div class="min-w-0">
                                    <h3 class="font-semibold leading-snug text-ink">{{ $m->name }}</h3>
                                    <p class="mt-1 text-sm text-muted">{{ $team->name }}</p>
                                    <p class="mt-2 text-sm tabular {{ ! $doc && $m->isPast() ? 'font-medium text-bad-ink' : 'text-muted' }}">{{ $m->due_at->format('H:i') }}@if (! $doc && $m->isPast()) · tenggat terlewat @endif</p>
                                </div>

                                {{-- Kanan: tombol unggah (popup pilih file); setelah terkumpul tetap tampil tetapi nonaktif --}}
                                <div class="sm:self-center">
                                    @if ($doc)
                                        <button type="button" class="btn btn-primary w-full disabled:cursor-not-allowed disabled:opacity-60" disabled><x-heroicon-o-check-circle class="h-5 w-5" /> Sudah Dikumpulkan</button>
                                    @elseif ($reason)
                                        <button type="button" class="btn btn-primary w-full disabled:cursor-not-allowed disabled:opacity-50" disabled><x-heroicon-o-arrow-up-tray class="h-5 w-5" /> Unggah file</button>
                                        <p class="mt-2 text-caption text-muted">{{ $reason }}</p>
                                    @else
                                        {{-- Pilih file dilakukan di popup setelah tombol diklik --}}
                                        <button type="button" class="btn btn-primary w-full" @click="toggle"><x-heroicon-o-arrow-up-tray class="h-5 w-5" /> Unggah file</button>
                                        <x-modal width="sm:max-w-lg">
                                            <x-slot:heading>Unggah file · {{ $m->name }}</x-slot:heading>
                                            @include('mahasiswa._document-upload', ['milestone' => $m, 'button' => 'Unggah file'])
                                        </x-modal>
                                    @endif
                                </div>

                                @if ($doc && $latest)
                                    {{-- Detail pengumpulan, selebar kartu --}}
                                    <div class="min-w-0 sm:col-span-2 sm:col-start-2">
                                        <p class="text-caption text-muted">
                                            Dikumpulkan oleh <span class="font-medium text-ink">{{ $latest->uploader?->name }}</span> · {{ $latest->created_at->translatedFormat('j M Y, H:i') }}@if ($latest->revision_no > 1) · revisi {{ $latest->revision_no }} @endif
                                            @if ($doc->is_late) · <span class="font-medium text-bad-ink">terlambat</span>@endif
                                        </p>

                                        <div class="mt-3 flex flex-wrap items-center gap-2">
                                            <a href="{{ $fileUrl }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm"><x-heroicon-o-eye class="h-4 w-4" /> Lihat file</a>
                                            <button type="button" class="btn btn-secondary btn-sm disabled:cursor-not-allowed disabled:opacity-50" @click="toggle" :aria-expanded="expanded" aria-controls="edit-{{ $m->id }}" @disabled($reason) @if ($reason) title="{{ $reason }}" @endif>
                                                <x-heroicon-o-pencil-square class="h-4 w-4" /> Edit
                                            </button>
                                            <form method="POST" action="{{ route('mahasiswa.documents.destroy', $doc) }}" data-confirm="Hapus pengumpulan “{{ $m->name }}”? File dipindahkan ke Trash dan milestone kembali belum dikumpulkan." data-confirm-label="Hapus">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-ghost btn-sm text-bad-ink"><x-heroicon-o-trash class="h-4 w-4" /> Hapus</button>
                                            </form>
                                        </div>
                                        @if ($driveEnabled && $latest->sync_status === 'gagal')
                                            <div class="mt-2 flex flex-wrap items-center gap-2 text-caption text-bad-ink">Sinkronisasi ke Google Drive gagal.
                                                <form method="POST" action="{{ route('mahasiswa.documents.retry', $latest) }}">@csrf<button class="link text-caption">Coba lagi</button></form>
                                            </div>
                                        @elseif ($driveEnabled && ! $doc->drive_file_id)
                                            <p class="mt-2 text-caption text-muted">Sedang disinkronkan ke Google Drive…</p>
                                        @endif

                                        {{-- Edit = unggah file pengganti --}}
                                        <x-modal width="sm:max-w-lg">
                                            <x-slot:heading>Ganti file · {{ $m->name }}</x-slot:heading>
                                            @include('mahasiswa._document-upload', ['milestone' => $m, 'button' => 'Simpan perubahan'])
                                        </x-modal>

                                        {{-- Feedback dosen (opsional) --}}
                                        <div class="mt-4 rounded-lg border border-rule px-4 py-3">
                                            <p class="text-sm font-medium text-ink">Feedback dosen</p>
                                            @if ($doc->feedback)
                                                <p class="mt-1 whitespace-pre-line text-sm text-ink">{{ $doc->feedback }}</p>
                                                <p class="mt-1 text-caption text-muted">{{ $doc->feedbackBy?->name }}@if ($doc->feedback_at) · {{ $doc->feedback_at->translatedFormat('j M Y, H:i') }}@endif</p>
                                            @else
                                                <p class="mt-1 text-sm text-muted">Belum ada feedback.</p>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @empty
                <x-empty :title="$range === 'semua' ? 'Belum ada milestone pengumpulan dokumen' : 'Tidak ada tenggat dalam '.$range.' hari ke depan'" icon="document-arrow-up" />
            @endforelse
        </section>
    @endif
</x-layouts.app>
