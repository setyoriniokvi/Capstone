<x-layouts.app title="Approval Topik" section="Admin · Approval topik">
    <x-page-head title="Approval topik" lede="Topik mandiri wajib diajukan setiap tim. Topik dosen baru terbit di bank topik setelah disetujui. Sistem menandai kata kunci yang tidak diperbolehkan." />

    <div class="grid gap-6 md:grid-cols-[minmax(0,1fr)_18rem]">
        <div class="min-w-0">
            <div class="mb-4 flex flex-wrap items-center gap-2">
                @foreach (['mandiri' => 'Topik mandiri', 'dosen' => 'Topik dosen'] as $k => $l)
                    <a href="{{ route('admin.topics.index', ['sumber' => $k, 'status' => $status]) }}" class="btn btn-sm {{ $source === $k ? 'btn-primary' : 'btn-secondary' }}">{{ $l }} <span class="tabular">{{ $pending[$k] ?? 0 }}</span></a>
                @endforeach
                <span class="mx-1 h-6 w-px bg-rule"></span>
                @foreach (['menunggu' => 'Menunggu', 'diterima' => 'Diterima', 'ditolak' => 'Ditolak', 'semua' => 'Semua'] as $k => $l)
                    <a href="{{ route('admin.topics.index', ['sumber' => $source, 'status' => $k]) }}" class="btn btn-sm {{ $status === $k ? 'btn-ghost bg-brand-tint' : 'btn-ghost' }}">{{ $l }}</a>
                @endforeach
            </div>

            <div class="panel">
                @if ($topics->isEmpty())
                    <x-empty title="Tidak ada topik pada filter ini" icon="light-bulb" />
                @else
                    <ul class="divide-y divide-rule">
                        @foreach ($topics as $topic)
                            <li class="px-4 py-4 sm:px-6">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm text-muted">
                                            @if ($topic->source === 'mandiri')
                                                <span class="code">{{ $topic->team?->code }}</span> · {{ $topic->team?->name }}
                                            @else
                                                <span class="code">{{ $topic->lecturer?->lecturerProfile?->lecturer_code }}</span> · {{ $topic->lecturer?->name }} · kuota <span class="tabular">{{ $topic->quota }}</span> tim
                                            @endif
                                            @if ($topic->field) · {{ $topic->field }}@endif
                                        </p>
                                        <h2 class="mt-1 font-semibold">{{ $topic->title }}</h2>
                                    </div>
                                    <x-badge :status="$topic->status" />
                                </div>
                                <p class="mt-2 max-w-[75ch] whitespace-pre-line text-sm text-ink">{{ $topic->description }}</p>
                                @if ($topic->keyword_hits)
                                    <p class="mt-3 flex flex-wrap items-center gap-2 text-sm text-bad-ink"><x-heroicon-o-flag class="h-4 w-4" />Kata kunci terlarang: @foreach ($topic->keyword_hits as $hit)<span class="rounded bg-bad-tint px-1.5 tabular text-caption">{{ $hit }}</span>@endforeach</p>
                                @endif
                                @if ($topic->review_notes)
                                    <p class="mt-2 text-sm text-muted">Catatan: {{ $topic->review_notes }}</p>
                                @endif
                                @if ($topic->status === 'menunggu')
                                    <div class="mt-3">
                                        <x-decision :action="route('admin.topics.decide', $topic)" title="Keputusan topik" approve-label="Setujui topik" reject-label="Tolak" trigger="Putuskan">“{{ $topic->title }}”</x-decision>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    {{ $topics->links() }}
                @endif
            </div>
        </div>

        <aside class="panel h-fit">
            <div class="panel-head"><h2 class="panel-title">Kata kunci terlarang</h2></div>
            <div class="panel-body">
                <form method="POST" action="{{ route('admin.keywords.store') }}" class="flex gap-2">
                    @csrf
                    <label for="keyword" class="sr-only">Kata kunci</label>
                    <input id="keyword" name="keyword" class="input" placeholder="mis. sistem informasi akademik" required>
                    <button class="btn btn-secondary">Tambah</button>
                </form>
                <ul class="mt-3 flex flex-wrap gap-1.5">
                    @forelse ($keywords as $k)
                        <li class="flex items-center gap-1 rounded bg-sunken py-0.5 pl-2 text-sm">
                            {{ $k->keyword }}
                            <form method="POST" action="{{ route('admin.keywords.destroy', $k) }}" data-confirm="Hapus kata kunci “{{ $k->keyword }}”?">@csrf @method('DELETE')<button class="grid h-7 w-7 place-items-center text-muted hover:text-bad-ink" aria-label="Hapus {{ $k->keyword }}"><x-heroicon-o-x-mark class="h-4 w-4" /></button></form>
                        </li>
                    @empty
                        <li class="text-sm text-muted">Belum ada kata kunci.</li>
                    @endforelse
                </ul>
            </div>
        </aside>
    </div>
</x-layouts.app>
