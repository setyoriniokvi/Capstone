{{-- Bar aksi sekaligus di atas tabel Menunggu Approval topik (di dalam Alpine "bulkApproval"):
     Setujui pilihan · Tolak pilihan (alasan wajib) — kotak centang baris memakai form="setujui-topik" — dan
     Tolak semua flag: menolak seluruh topik menunggu pada tab ini yang ditandai kata kunci terlarang
     (alasan otomatis menyebut kata kuncinya). Butuh $pending, $tab. --}}
@php $flagged = $pending->filter(fn ($t) => ! empty($t->keyword_hits))->count(); @endphp
<div class="flex flex-wrap items-center justify-between gap-3 border-t border-rule bg-sunken px-5 py-3 sm:px-6">
    <p class="text-sm text-muted"><span class="tabular font-medium text-ink" x-text="selected">0</span> topik dipilih</p>
    <div class="flex flex-wrap items-center gap-2">
        <form id="setujui-topik" method="POST" action="{{ route('admin.topics.approve-many') }}" class="flex flex-wrap items-center gap-2">
            @csrf
            {{-- Setujui pilihan --}}
            <div x-data="disclosure">
                <button type="button" class="btn btn-primary btn-sm disabled:cursor-not-allowed disabled:opacity-50" @click="toggle" :disabled="noneSelected" disabled title="Pilih topik terlebih dahulu">
                    <x-heroicon-o-check class="h-4 w-4" /> Setujui pilihan
                </button>
                <x-modal width="sm:max-w-md">
                    <x-slot:heading>Setujui topik terpilih</x-slot:heading>
                    <p class="text-sm text-ink">Setujui <span class="tabular font-semibold" x-text="selected">0</span> topik yang dipilih?</p>
                    <div class="mt-5 flex justify-end gap-3 border-t border-rule pt-4">
                        <button type="button" class="btn btn-ghost" @click="close">Batal</button>
                        <button class="btn btn-primary" formaction="{{ route('admin.topics.approve-many') }}" data-once>Setujui</button>
                    </div>
                </x-modal>
            </div>
            {{-- Tolak pilihan (alasan wajib, dikirim ke semua topik terpilih) --}}
            <div x-data="disclosure">
                <button type="button" class="btn btn-secondary btn-sm text-bad-ink disabled:cursor-not-allowed disabled:opacity-50" @click="toggle" :disabled="noneSelected" disabled title="Pilih topik terlebih dahulu">
                    <x-heroicon-o-x-mark class="h-4 w-4" /> Tolak pilihan
                </button>
                <x-modal width="sm:max-w-lg">
                    <x-slot:heading>Tolak topik terpilih</x-slot:heading>
                    <p class="text-sm text-muted"><span class="tabular font-semibold text-ink" x-text="selected">0</span> topik dipilih. Alasan di bawah dikirim ke setiap pengusul.</p>
                    <label class="field-label mt-4" for="alasan-tolak-pilihan">Alasan penolakan<span class="text-bad-ink" aria-hidden="true"> *</span></label>
                    <textarea id="alasan-tolak-pilihan" name="notes" rows="4" class="input" maxlength="2000"></textarea>
                    <div class="mt-5 flex justify-end gap-3 border-t border-rule pt-4">
                        <button type="button" class="btn btn-ghost" @click="close">Batal</button>
                        <button class="btn btn-danger" formaction="{{ route('admin.topics.reject-many') }}" data-once>Tolak</button>
                    </div>
                </x-modal>
            </div>
        </form>

        {{-- Tolak semua topik ber-flag kata kunci terlarang pada tab ini --}}
        <form method="POST" action="{{ route('admin.topics.reject-flagged') }}"
              data-confirm="Tolak {{ $flagged }} topik yang mengandung kata kunci terlarang? Alasan penolakan otomatis menyebut kata kuncinya." data-confirm-label="Tolak semua">
            @csrf
            <input type="hidden" name="source" value="{{ $tab }}">
            <button class="btn btn-secondary btn-sm text-bad-ink disabled:cursor-not-allowed disabled:opacity-50" @disabled($flagged === 0) @if ($flagged === 0) title="Tidak ada topik yang ditandai kata kunci terlarang" @endif>
                <x-heroicon-o-flag class="h-4 w-4" /> Tolak semua flag @if ($flagged)<span class="tabular">({{ $flagged }})</span>@endif
            </button>
        </form>
    </div>
</div>
