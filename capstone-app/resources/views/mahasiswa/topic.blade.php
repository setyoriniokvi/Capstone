{{-- F22 / F23 / F25 — menu Topik: tab "Topik Tim" (mahasiswa/_topic-mine) dan "Topik Dosen" (mahasiswa/_topic-lecturer). --}}
<x-layouts.app title="Topik" section="Mahasiswa · Topik">
    <x-page-head title="Topik Capstone" />

    <nav class="mb-6 inline-flex rounded-xl border border-rule bg-sunken p-1" aria-label="Tab topik">
        @foreach (['saya' => 'Topik Tim', 'dosen' => 'Topik Dosen'] as $key => $label)
            <a href="{{ route('mahasiswa.topic.index', $key === 'dosen' ? ['tab' => 'dosen'] : []) }}" @if ($tab === $key) aria-current="page" @endif
               class="inline-flex min-h-[40px] items-center rounded-lg px-4 text-sm font-medium {{ $tab === $key ? 'bg-surface text-ink shadow-panel' : 'text-muted hover:text-ink' }}">{{ $label }}</a>
        @endforeach
    </nav>

    @foreach (['topic', 'window'] as $key)
        @error($key)<p class="mb-6 rounded-lg border border-bad-line bg-bad-tint px-4 py-3 text-sm text-bad-ink">{{ $message }}</p>@enderror
    @endforeach

    @include($tab === 'dosen' ? 'mahasiswa._topic-lecturer' : 'mahasiswa._topic-mine')
</x-layouts.app>
