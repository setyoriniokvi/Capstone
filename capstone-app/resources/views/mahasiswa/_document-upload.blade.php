{{-- Form unggah dokumen milestone dengan progres (Alpine "uploader"). Butuh $milestone dan $button (teks tombol). --}}
<form x-data="uploader" @submit.prevent="submit" method="POST" action="{{ route('mahasiswa.documents.store', $milestone) }}" enctype="multipart/form-data" class="space-y-2">
    @csrf
    <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
    <label class="sr-only" for="file-{{ $milestone->id }}-{{ \Illuminate\Support\Str::slug($button) }}">{{ $button }} — {{ $milestone->name }}</label>
    <input id="file-{{ $milestone->id }}-{{ \Illuminate\Support\Str::slug($button) }}" type="file" name="file" accept="{{ $milestone->acceptAttribute() }}" class="input bg-surface text-sm" @change="pick" required>
    <p class="text-caption text-muted">{{ $milestone->fileTypesLabel() }} · maks. {{ config('capstone.upload.document_max_kb') / 1024 }} MB @if ($milestone->isPast()) · <span class="text-warn-ink">ditandai terlambat</span>@endif</p>
    <button type="submit" class="btn btn-primary w-full" :disabled="busy"><x-heroicon-o-arrow-up-tray class="h-5 w-5" /> {{ $button }}</button>
    <div x-cloak x-show="busy" role="progressbar" aria-label="Progres unggah" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="progress">
        <div class="h-1.5 overflow-hidden rounded bg-rule"><div class="h-full origin-left bg-brand transition-transform" :style="barStyle"></div></div>
        <p class="mt-1 text-caption text-muted">Mengunggah <span x-text="percent"></span></p>
    </div>
    <p x-cloak x-show="hasError" x-text="error" class="field-error" role="alert"></p>
</form>
