@props(['label', 'name', 'hint' => null, 'required' => false])
<div {{ $attributes->merge(['class' => 'min-w-0']) }}>
    <label for="{{ $name }}" class="field-label">{{ $label }}@if ($required)<span class="text-bad-ink" aria-hidden="true"> *</span>@endif</label>
    {{ $slot }}
    @if ($hint)
        <p class="field-hint">{{ $hint }}</p>
    @endif
    @error($name)
        <p class="field-error">{{ $message }}</p>
    @enderror
</div>
