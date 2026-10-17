{{-- Dialog konfirmasi tunggal untuk form ber-atribut data-confirm (lihat resources/js/app.js). --}}
<div x-data="confirmDialog" x-cloak x-show="open"
     x-transition:enter="motion-fade" x-transition:enter-start="opacity-0"
     x-transition:leave="motion-fade" x-transition:leave-end="opacity-0"
     class="dialog-scrim" @click.self="cancel" @keydown.escape.window="cancel">
    <div role="alertdialog" aria-modal="true" aria-labelledby="confirm-title" aria-describedby="confirm-message" class="dialog-sheet motion-rise">
        <div class="px-4 py-4 sm:px-6">
            <h2 id="confirm-title" class="text-base font-semibold">Konfirmasi</h2>
            <p id="confirm-message" class="mt-2 text-sm text-muted" x-text="message"></p>
        </div>
        <div class="flex flex-col-reverse gap-2 border-t border-rule px-4 py-3 sm:flex-row sm:justify-end sm:px-6">
            <button type="button" class="btn btn-ghost" x-ref="cancel" @click="cancel">Batal</button>
            <button type="button" class="btn btn-danger" @click="accept" x-text="label"></button>
        </div>
    </div>
</div>
