@php
    $syncLabel = fn ($v) => match (true) {
        ! $driveEnabled => 'Tersimpan di server',
        $v->sync_status === 'berhasil' => 'Tersinkron ke Drive',
        $v->sync_status === 'gagal' => 'Sinkronisasi gagal',
        $v->sync_status === 'proses' => 'Sedang disinkronkan',
        $v->sync_status === 'dilewati' => 'Digantikan revisi baru',
        default => 'Menunggu sinkronisasi',
    };
@endphp
<x-layouts.app title="Dokumen Milestone" section="Mahasiswa · Dokumen">
    <x-page-head title="Dokumen milestone" lede="Setiap file terhubung dengan akun Anda. Unggah ulang untuk revisi — file di Google Drive diperbarui, bukan diduplikasi. Anda hanya dapat membuka dokumen milik sendiri." />

    @if ($gate)
        <div class="panel"><x-empty :title="$gate" icon="lock-closed" /></div>
    @elseif ($milestones->isEmpty())
        <div class="panel"><x-empty title="Belum ada milestone pengumpulan dokumen" icon="document-arrow-up" /></div>
    @else
        <div class="space-y-4">
            @foreach ($milestones as $m)
                @php
                    $doc = $mine->get($m->id);
                    $active = $doc && $doc->state === 'aktif' ? $doc : null;
                    $latest = $active?->versions->first();
                    $others = $teammates->get($m->id, collect());
                    $locked = $m->document_type === 'materi_presentasi' && $approvedLogbooks < $minLogbooks;
                @endphp
                <section class="panel">
                    <div class="flex flex-wrap items-start gap-4 px-4 py-4 sm:px-5">
                        <div class="min-w-0 flex-1">
                            <h2 class="font-semibold">{{ $m->name }}</h2>
                            <p class="text-sm text-muted">
                                {{ config('capstone.document_types.'.$m->document_type) }} ·
                                @if (! $m->hasStarted()) dibuka {{ $m->starts_at->translatedFormat('j M Y H:i') }} ·@endif
                                tenggat <span class="{{ $m->isPast() && ! $active ? 'font-medium text-bad-ink' : '' }}">{{ $m->due_at->translatedFormat('l, j M Y H:i') }}</span>
                            </p>
                        </div>
                        @if ($active)
                            <x-badge :status="$active->review_status" />
                            @if ($active->is_late)<x-badge status="terlambat" />@endif
                        @elseif ($m->isPast())
                            <x-badge status="terlambat" label="Belum dikumpulkan" />
                        @endif
                    </div>

                    @if ($active && $latest)
                        <div class="grid gap-4 border-t border-rule px-4 py-4 sm:px-5 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                            <div class="min-w-0">
                                <p class="text-sm text-muted">File terkini · revisi <span class="font-mono">{{ $latest->revision_no }}</span></p>
                                <a href="{{ route('documents.download', $latest) }}" class="link mt-1 inline-block break-all">{{ $latest->stored_name }}</a>
                                <p class="mt-1 text-caption text-muted">{{ \App\Support\Format::bytes($latest->size) }} · {{ $latest->created_at->translatedFormat('j M Y H:i') }}</p>
                                <p class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                                    <x-badge :status="$driveEnabled ? $latest->sync_status : 'netral'" :label="$syncLabel($latest)" />
                                    @if ($driveEnabled && $latest->sync_status === 'gagal')
                                        <form method="POST" action="{{ route('mahasiswa.documents.retry', $latest) }}">@csrf<button class="btn btn-ghost btn-sm">Coba lagi</button></form>
                                    @endif
                                </p>
                                @if ($driveEnabled && $latest->sync_status === 'gagal' && $active->current_version_id && $active->current_version_id !== $latest->id)
                                    <p class="mt-1 text-caption text-muted">File di Drive masih revisi {{ $active->currentVersion?->revision_no }} sampai sinkronisasi berhasil.</p>
                                @endif
                            </div>
                            <div class="min-w-0 rounded border border-rule bg-sunken px-3 py-2">
                                <p class="text-sm text-muted">Feedback dosen</p>
                                <p class="mt-1 whitespace-pre-line text-sm">{{ $active->feedback ?: 'Belum ada feedback.' }}</p>
                                @if ($active->feedback_at)<p class="mt-1 text-caption text-muted">{{ $active->feedback_at->translatedFormat('j M Y H:i') }}</p>@endif
                            </div>
                        </div>
                        @if ($active->versions->count() > 1)
                            <div x-data="disclosure" class="border-t border-rule px-4 py-2 sm:px-5">
                                <button type="button" class="btn btn-ghost btn-sm -ml-3" @click="toggle">Riwayat revisi ({{ $active->versions->count() }})</button>
                                <ol x-cloak x-show="expanded" class="mb-2 space-y-1 text-sm">
                                    @foreach ($active->versions as $v)
                                        <li class="flex flex-wrap gap-x-3"><span class="font-mono text-muted">r{{ $v->revision_no }}</span><a class="link" href="{{ route('documents.download', $v) }}">{{ $v->original_name }}</a><span class="text-muted">{{ $v->created_at->translatedFormat('j M H:i') }} · {{ $syncLabel($v) }}</span></li>
                                    @endforeach
                                </ol>
                            </div>
                        @endif
                    @endif

                    @if ($others->isNotEmpty())
                        <p class="border-t border-rule px-4 py-2 text-sm text-muted sm:px-5">Juga dikumpulkan oleh: {{ $others->pluck('owner.name')->implode(', ') }}</p>
                    @endif

                    <div class="border-t border-rule bg-sunken px-4 py-3 sm:px-5">
                        @if ($locked)
                            <p class="text-sm text-muted">Terkunci — minimal {{ $minLogbooks }} log bimbingan harus disetujui sebelum presentasi akhir ({{ $approvedLogbooks }}/{{ $minLogbooks }}).</p>
                        @elseif (! $m->hasStarted())
                            <p class="text-sm text-muted">Pengumpulan belum dibuka.</p>
                        @else
                            <form x-data="uploader" @submit.prevent="submit" method="POST" action="{{ route('mahasiswa.documents.store', $m) }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-3">
                                @csrf
                                <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                                <div class="min-w-0 flex-1">
                                    <label class="field-label" for="file-{{ $m->id }}">{{ $active ? 'Unggah revisi' : 'Unggah dokumen' }}</label>
                                    <input id="file-{{ $m->id }}" type="file" name="file" accept=".pdf,.docx,.pptx,.jpg,.jpeg" class="input bg-surface py-2" @change="pick" required>
                                    <p class="field-hint">PDF, DOCX, PPTX, atau JPG · maks. {{ config('capstone.upload.document_max_kb') / 1024 }} MB @if ($m->isPast())· <span class="text-warn-ink">melewati tenggat, akan ditandai terlambat</span>@endif</p>
                                </div>
                                <button type="submit" class="btn btn-primary" :disabled="busy">{{ $active ? 'Unggah revisi' : 'Unggah' }}</button>
                                <div x-cloak x-show="busy" class="w-full" role="progressbar" aria-label="Progres unggah" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="progress">
                                    <div class="h-1.5 overflow-hidden rounded bg-rule"><div class="h-full origin-left bg-brand transition-transform" :style="barStyle"></div></div>
                                    <p class="mt-1 text-caption text-muted">Mengunggah <span x-text="percent"></span></p>
                                </div>
                                <p x-cloak x-show="hasError" x-text="error" class="field-error w-full" role="alert"></p>
                            </form>
                            @if ($active)
                                <form method="POST" action="{{ route('mahasiswa.documents.destroy', $active) }}" class="mt-2">@csrf @method('DELETE')
                                    <button class="btn btn-ghost btn-sm -ml-3 text-bad-ink">Hapus dokumen (pindah ke Trash)</button>
                                </form>
                            @endif
                        @endif
                    </div>
                </section>
            @endforeach
        </div>
    @endif
</x-layouts.app>
