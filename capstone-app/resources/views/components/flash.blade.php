@if (session('status'))
    <div role="status" class="motion-rise mb-6 flex items-start gap-3 rounded-lg border border-ok-line bg-ok-tint px-4 py-3 text-sm text-ok-ink">
        <x-heroicon-o-check-circle class="mt-px h-5 w-5 shrink-0" />
        <p>{{ session('status') }}</p>
    </div>
@endif
@if ($errors->any())
    <div role="alert" class="motion-rise mb-6 flex items-start gap-3 rounded-lg border border-bad-line bg-bad-tint px-4 py-3 text-sm text-bad-ink">
        <x-heroicon-o-exclamation-triangle class="mt-px h-5 w-5 shrink-0" />
        <div>
            @if ($errors->count() === 1)
                <p>{{ $errors->first() }}</p>
            @else
                <p class="font-medium">Periksa kembali isian berikut:</p>
                <ul class="mt-1 list-disc space-y-0.5 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@endif
