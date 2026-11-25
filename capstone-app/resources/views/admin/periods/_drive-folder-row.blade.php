{{-- Satu baris Folder Google Drive Database (Alpine "driveFolders"): tautan folder untuk satu milestone dokumen.
     Butuh $key (id milestone, atau kunci draf "d1"… pada periode baru), $label (nama milestone), $value, $hidden. --}}
<div data-folder-row data-key="{{ $key }}" data-label="{{ $label }}" @if ($hidden) hidden @endif>
    <label class="field-label" for="drive_folders.{{ $key }}" data-folder-label>{{ $label }}</label>
    <div class="flex gap-2">
        <input id="drive_folders.{{ $key }}" form="period-form" name="drive_folders[{{ $key }}]" class="input min-w-0 flex-1" inputmode="url"
               value="{{ $value }}" placeholder="https://drive.google.com/drive/folders/…">
        <button type="button" class="btn btn-ghost btn-sm shrink-0 self-center text-bad-ink" @click="remove" aria-label="Hapus folder {{ $label }}">Hapus</button>
    </div>
    @error("drive_folders.$key")<p class="field-error">{{ $message }}</p>@enderror
</div>
