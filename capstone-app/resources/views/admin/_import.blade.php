{{-- Unggah impor Excel/CSV (F3, F4). --}}
<div x-data="disclosure" class="panel mb-6">
    <button type="button" class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left sm:px-6" @click="toggle" :aria-expanded="expanded">
        <span class="font-medium">Impor {{ $label }} dari Excel / CSV</span>
        <x-heroicon-o-arrow-up-tray class="h-5 w-5 text-muted" />
    </button>
    <x-modal>
        <x-slot:heading>Impor {{ $label }} dari Excel / CSV</x-slot:heading>
        <p class="text-sm text-muted">Kolom: <span class="code">{{ implode(' | ', \App\Services\Import\ImportService::TEMPLATES[$type]) }}</span>. {{ $hint ?? '' }}</p>
        <form method="POST" action="{{ route('admin.imports.preview', $type) }}" enctype="multipart/form-data" class="mt-4 flex flex-wrap items-end gap-3">
            @csrf
            <div class="min-w-0 flex-1">
                <label class="field-label" for="file-{{ $type }}">File .csv atau .xlsx</label>
                <input id="file-{{ $type }}" type="file" name="file" accept=".csv,.xlsx" class="input py-2" required>
            </div>
            <button class="btn btn-primary" data-once>Pratinjau</button>
            <a href="{{ route('admin.imports.template', $type) }}" class="btn btn-ghost"><x-heroicon-o-arrow-down-tray class="h-5 w-5" />Template</a>
        </form>
    </x-modal>
</div>
