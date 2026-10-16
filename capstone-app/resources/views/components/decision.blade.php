{{-- Modal konfirmasi approval / penolakan (PRD §10.3). --}}
@props(['action', 'title', 'approveLabel' => 'Setujui', 'rejectLabel' => 'Tolak', 'notesRequiredOnReject' => true, 'approveValue' => 'approve', 'rejectValue' => 'reject', 'field' => 'decision', 'trigger' => 'Tinjau'])
<div x-data="decision" class="inline-block">
    <button type="button" class="btn btn-secondary btn-sm" @click="show">{{ $trigger }}</button>
    <div x-cloak x-show="open" class="fixed inset-0 z-50 grid place-items-end bg-scrim p-0 sm:place-items-center sm:p-6" @keydown.escape.window="hide">
        <div x-ref="dialog" role="dialog" aria-modal="true" aria-label="{{ $title }}" @click.outside="hide"
             class="w-full max-w-lg rounded-t-lg bg-surface text-left shadow-xl sm:rounded-lg">
            <form method="POST" action="{{ $action }}">
                @csrf
                <div class="border-b border-rule px-5 py-4">
                    <h2 class="text-base font-semibold">{{ $title }}</h2>
                    @if ($slot->isNotEmpty())
                        <div class="mt-2 text-sm text-muted">{{ $slot }}</div>
                    @endif
                </div>
                <div class="px-5 py-4">
                    <label class="field-label" for="notes-{{ md5($action) }}">Catatan</label>
                    <textarea id="notes-{{ md5($action) }}" name="notes" rows="3" class="input" placeholder="{{ $notesRequiredOnReject ? 'Wajib diisi bila menolak / meminta revisi' : 'Opsional' }}"></textarea>
                </div>
                <div class="flex flex-col-reverse gap-2 border-t border-rule px-5 py-3 sm:flex-row sm:justify-end">
                    <button type="button" class="btn btn-ghost" @click="hide">Batal</button>
                    <button type="submit" name="{{ $field }}" value="{{ $rejectValue }}" class="btn btn-secondary text-bad-ink" data-once>{{ $rejectLabel }}</button>
                    <button type="submit" name="{{ $field }}" value="{{ $approveValue }}" class="btn btn-primary" data-once>{{ $approveLabel }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
