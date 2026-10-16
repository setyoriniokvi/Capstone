@props(['action'])
<form method="GET" action="{{ $action }}" {{ $attributes->merge(['class' => 'flex flex-wrap items-end gap-3']) }}>
    {{ $slot }}
    <button type="submit" class="btn btn-secondary">Terapkan</button>
    @if (count(request()->query()))
        <a href="{{ $action }}" class="btn btn-ghost">Reset</a>
    @endif
</form>
