{{-- F27 — Log bimbingan tim. Judul "Log Bimbingan", nama tim di bawahnya; "Tambah sesi baru" membuka formulir (tanggal + catatan) di atas;
     kartu progres "x sesi disetujui"; daftar sesi: catatan, tanggal, status (Menunggu/Disetujui/Ditolak),
     Ubah / Hapus (nonaktif bila disetujui), dan feedback dosen (opsional). --}}
@php
    // Status "revisi" di basis data ditampilkan sebagai "Ditolak".
    $statusLabel = ['menunggu' => 'Menunggu', 'disetujui' => 'Disetujui', 'revisi' => 'Ditolak'];
@endphp
<x-layouts.app title="Log Bimbingan" section="Mahasiswa · Log bimbingan">
    @if (! $team || $team->status !== 'diterima')
        <x-page-head title="Log Bimbingan" :lede="$team?->name" />
        <div class="panel"><x-empty title="Log bimbingan tersedia setelah tim diterima" icon="clipboard-document-check" /></div>
    @else
        @php $canAdd = (bool) $team->supervisor; @endphp
        <div x-data="registrationForm" data-open="{{ $errors->hasAny(['session_date', 'activity']) && ! old('_log') ? 'true' : 'false' }}">
            <x-page-head title="Log Bimbingan" :lede="$team->name">
                <x-slot:actions>
                    <button type="button" class="btn btn-primary disabled:cursor-not-allowed disabled:opacity-50" @click="toggle" aria-controls="form-sesi"
                            @disabled(! $canAdd) @unless ($canAdd) title="Dapat diisi setelah dosen pembimbing ditetapkan" @endunless>
                        <x-heroicon-o-plus class="h-5 w-5" /> Tambah sesi baru
                    </button>
                </x-slot:actions>
            </x-page-head>

            @error('logbook')<p class="mb-6 rounded-lg border border-bad-line bg-bad-tint px-4 py-3 text-sm text-bad-ink">{{ $message }}</p>@enderror

            @if ($canAdd)
<x-modal>
    <x-slot:heading>Tambah sesi bimbingan</x-slot:heading>
                    <form method="POST" action="{{ route('mahasiswa.logbooks.store') }}" class="space-y-4">
                        @csrf
                        @include('mahasiswa._logbook-fields', ['log' => null])
                        <div class="flex flex-wrap justify-end gap-3 border-t border-rule pt-4">
                            <button type="button" class="btn btn-ghost" @click="close">Batal</button>
                            <button class="btn btn-primary" data-once>Kirim ke pembimbing</button>
                        </div>
                    </form>
                </x-modal>
            @endif

            {{-- Progres sesi disetujui --}}
            <section class="panel mb-6 p-5 sm:p-6" aria-label="Sesi disetujui">
                <p class="text-lg font-semibold text-ink"><span class="tabular">{{ $approved }}</span> sesi disetujui</p>
                <x-progress-bar class="mt-4" :value="$approved" :max="$minLogbooks" label="Sesi bimbingan disetujui" />
            </section>

            <section class="panel" aria-label="Riwayat sesi bimbingan">
                @if ($team->logbooks->isEmpty())
                    <x-empty title="Belum ada sesi bimbingan" icon="clipboard-document-check" />
                @else
                    <ol class="divide-y divide-rule px-5 sm:px-6">
                        @foreach ($team->logbooks->sortByDesc('session_no') as $log)
                            @php $locked = $log->status === 'disetujui'; @endphp
                            <li x-data="registrationForm" data-open="{{ $errors->hasAny(['session_date', 'activity']) && old('_log') == $log->id ? 'true' : 'false' }}" class="grid gap-x-4 gap-y-3 py-5 sm:grid-cols-[3.5rem_minmax(0,1fr)]">
                                <span class="hidden h-14 w-14 place-items-center rounded-xl bg-brand-wash text-brand sm:grid" title="Sesi {{ $log->session_no }}">
                                    <x-icons.hand-book class="h-8 w-8" />
                                </span>
                                <div class="min-w-0">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="whitespace-pre-line font-semibold leading-snug text-ink">{{ $log->activity }}</p>
                                            <p class="mt-1 text-sm text-muted"><time datetime="{{ $log->session_date->toDateString() }}">{{ $log->session_date->translatedFormat('j M Y') }}</time><span class="sr-only"> · sesi {{ $log->session_no }}</span></p>
                                        </div>
                                        <x-badge :status="$log->status" :label="$statusLabel[$log->status] ?? ucfirst($log->status)" class="shrink-0" />
                                    </div>

                                    <div class="mt-3 flex flex-wrap items-center gap-2">
                                        <button type="button" class="btn btn-secondary btn-sm disabled:cursor-not-allowed disabled:opacity-50" @click="toggle" :aria-expanded="expanded" aria-controls="ubah-{{ $log->id }}"
                                                @disabled($locked) @if ($locked) title="Sesi yang sudah disetujui tidak dapat diubah" @endif>
                                            <x-heroicon-o-pencil-square class="h-4 w-4" /> Ubah
                                        </button>
                                        <form method="POST" action="{{ route('mahasiswa.logbooks.destroy', $log) }}" data-confirm="Hapus log bimbingan ini?" data-confirm-label="Hapus">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-ghost btn-sm text-bad-ink disabled:cursor-not-allowed disabled:opacity-50" @disabled($locked) @if ($locked) title="Sesi yang sudah disetujui tidak dapat dihapus" @endif>
                                                <x-heroicon-o-trash class="h-4 w-4" /> Hapus
                                            </button>
                                        </form>
                                    </div>

                                    @unless ($locked)
                                        <x-modal>
                                        <x-slot:heading>Ubah sesi bimbingan</x-slot:heading>
                                        <form id="ubah-{{ $log->id }}" method="POST" action="{{ route('mahasiswa.logbooks.update', $log) }}" class="space-y-4">
                                            @csrf @method('PUT')
                                            <input type="hidden" name="_log" value="{{ $log->id }}">
                                            @include('mahasiswa._logbook-fields', ['log' => $log])
                                            <div class="flex flex-wrap justify-end gap-3">
                                                <button type="button" class="btn btn-ghost" @click="close">Batal</button>
                                                <button class="btn btn-primary">Simpan</button>
                                            </div>
                                        </form>
                                        </x-modal>
                                    @endunless

                                    {{-- Feedback dosen (opsional) --}}
                                    <div class="mt-4 rounded-lg border border-rule px-4 py-3">
                                        <p class="text-sm font-medium text-ink">Feedback dosen</p>
                                        @if ($log->feedback)
                                            <p class="mt-1 whitespace-pre-line text-sm text-ink">{{ $log->feedback }}</p>
                                            <p class="mt-1 text-caption text-muted">{{ $log->reviewer?->name }}@if ($log->reviewed_at) · {{ $log->reviewed_at->translatedFormat('j M Y, H:i') }}@endif</p>
                                        @else
                                            <p class="mt-1 text-sm text-muted">Belum ada feedback.</p>
                                        @endif
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        </div>
    @endif
</x-layouts.app>
