{{-- Dialog konfirmasi tunggal untuk form ber-atribut data-confirm (lihat resources/js/app.js).
     data-confirm-notes="nama_field" menampilkan kolom catatan opsional yang ikut dikirim bersama form. --}}
<div x-data="confirmDialog" x-cloak x-show="open"
     x-transition:enter="motion-fade" x-transition:enter-start="opacity-0"
     x-transition:leave="motion-fade" x-transition:leave-end="opacity-0"
     class="dialog-scrim" @click.self="cancel" @keydown.escape.window="cancel">
    <div role="alertdialog" aria-modal="true" aria-labelledby="confirm-title" aria-describedby="confirm-message" class="dialog-sheet motion-rise">
        <div class="px-4 py-4 sm:px-6">
            <h2 id="confirm-title" class="text-base font-semibold">Konfirmasi</h2>
            <p id="confirm-message" class="mt-2 text-sm text-muted" x-text="message"></p>
            <div x-show="withNotes" class="mt-4">
                <label class="field-label" for="confirm-notes" x-text="notesLabel"></label>
                <textarea id="confirm-notes" x-ref="notes" rows="3" class="input" maxlength="2000"></textarea>
            </div>
        </div>
        <div class="flex flex-col-reverse gap-2 border-t border-rule px-4 py-3 sm:flex-row sm:justify-end sm:px-6">
            <button type="button" class="btn btn-ghost" x-ref="cancel" @click="cancel">Batal</button>
            <button type="button" class="btn" :class="acceptClass" @click="accept" x-text="label"></button>
        </div>
    </div>
</div>
