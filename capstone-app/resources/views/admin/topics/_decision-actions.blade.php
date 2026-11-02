{{-- Aksi baris "Menunggu Approval" topik: Tolak (modal, catatan wajib) & Terima (konfirmasi). Butuh $topic.
     Dipakai di dalam baris ber-x-data="disclosure" (modal Tolak memakai state baris). --}}
<div class="mx-auto grid max-w-[9rem] grid-cols-2 gap-2">
    <button type="button" class="btn btn-secondary btn-sm w-full px-2 text-bad-ink" @click="toggle">Tolak</button>
    <form method="POST" action="{{ route('admin.topics.decide', $topic) }}" data-confirm="Setujui topik “{{ $topic->title }}”?" data-confirm-label="Setujui" data-confirm-tone="positive">
        @csrf<input type="hidden" name="decision" value="approve">
        <button class="btn btn-primary btn-sm w-full px-2">Terima</button>
    </form>
</div>
<x-modal width="sm:max-w-lg">
    <x-slot:heading>Tolak topik</x-slot:heading>
    <form method="POST" action="{{ route('admin.topics.decide', $topic) }}" class="space-y-4">
        @csrf
        <input type="hidden" name="decision" value="reject">
        <p class="text-sm text-muted">“{{ $topic->title }}”</p>
        <div>
            <label class="field-label" for="alasan-topik-{{ $topic->id }}">Alasan penolakan<span class="text-bad-ink" aria-hidden="true"> *</span></label>
            <textarea id="alasan-topik-{{ $topic->id }}" name="notes" rows="4" class="input" maxlength="2000" required></textarea>
        </div>
        <div class="flex justify-end gap-3 border-t border-rule pt-4">
            <button type="button" class="btn btn-ghost" @click="close">Batal</button>
            <button class="btn btn-danger" data-once>Tolak topik</button>
        </div>
    </form>
</x-modal>
