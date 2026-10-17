@php
    $topic = $team?->independentTopic;
    $canEdit = $team?->status === 'diterima' && (! $topic || $topic->status !== 'diterima');
@endphp
<x-layouts.app title="Topik" section="Mahasiswa · Topik">
    <x-page-head title="Topik Capstone" lede="Setiap tim wajib mengajukan topik mandiri. Setelah diterima, tim boleh melamar topik dosen; bila diterima, topik dosen menggantikan topik mandiri." />

    @if (! $team || $team->status !== 'diterima')
        <div class="panel"><x-empty title="Pengajuan topik belum dibuka untuk tim Anda" icon="light-bulb">Topik dapat diajukan setelah registrasi tim diterima admin.</x-empty></div>
    @else
        {{-- F25 — topik final & pembimbing --}}
        <section class="panel mb-6">
            <div class="grid gap-4 panel-body md:grid-cols-[minmax(0,1fr)_16rem]">
                <div class="min-w-0">
                    <p class="text-sm text-muted">Topik final</p>
                    <p class="mt-1 text-h2">{{ $team->finalTopic?->title ?? 'Belum ditetapkan' }}</p>
                    @if ($team->finalTopic)
                        <p class="mt-1 text-sm text-muted">{{ $team->finalTopic->source === 'dosen' ? 'Topik dosen · '.$team->finalTopic->lecturer?->name : 'Topik mandiri' }}</p>
                    @endif
                </div>
                <div class="border-t border-rule pt-4 md:border-l md:border-t-0 md:pl-5 md:pt-0">
                    <p class="text-sm text-muted">Dosen pembimbing</p>
                    <p class="mt-1 font-medium">{{ $team->supervisor?->lecturer->name ?? 'Belum ditetapkan' }}</p>
                    @if ($team->supervisor)<p class="text-sm text-muted">{{ $team->supervisor->lecturer->email }}</p>@endif
                </div>
            </div>
        </section>

        <div class="grid gap-6 md:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]">
            <section class="panel">
                <div class="panel-head">
                    <h2 class="panel-title">Topik mandiri</h2>
                    @if ($topic)<x-badge :status="$topic->status" />@endif
                </div>
                <div class="panel-body">
                    @if ($topic?->review_notes)
                        <p class="mb-4 rounded border border-rule bg-sunken px-3 py-2 text-sm"><span class="font-medium">Catatan admin:</span> {{ $topic->review_notes }}</p>
                    @endif
                    @if ($canEdit)
                        <form method="POST" action="{{ route('mahasiswa.topic.store') }}" class="space-y-4">
                            @csrf
                            <x-field label="Judul topik" name="title" required><input id="title" name="title" class="input" value="{{ old('title', $topic?->title) }}" required maxlength="255"></x-field>
                            <x-field label="Bidang / kategori" name="field"><input id="field" name="field" class="input" value="{{ old('field', $topic?->field) }}"></x-field>
                            <x-field label="Deskripsi permasalahan" name="description" required hint="Topik wajib mencakup permasalahan sistem terintegrasi. Jelaskan konteks, problem owner, dan gap permasalahan.">
                                <textarea id="description" name="description" rows="7" class="input" required>{{ old('description', $topic?->description) }}</textarea>
                            </x-field>
                            <button class="btn btn-primary" data-once>{{ $topic ? ($topic->status === 'ditolak' ? 'Ajukan ulang' : 'Perbarui pengajuan') : 'Ajukan topik' }}</button>
                        </form>
                    @else
                        <h3 class="font-semibold">{{ $topic->title }}</h3>
                        @if ($topic->field)<p class="text-sm text-muted">{{ $topic->field }}</p>@endif
                        <p class="mt-3 whitespace-pre-line text-sm">{{ $topic->description }}</p>
                        <p class="mt-4 text-caption text-muted">Ditinjau {{ $topic->reviewed_at?->translatedFormat('j F Y H:i') }} oleh {{ $topic->reviewer?->name }}</p>
                    @endif
                </div>
            </section>

            <section class="panel h-fit">
                <div class="panel-head"><h2 class="panel-title">Lamaran topik dosen</h2><a class="link text-sm" href="{{ route('mahasiswa.bank.index') }}">Bank topik</a></div>
                @if ($team->applications->isEmpty())
                    <p class="panel-body text-sm text-muted">Opsional. {{ $topic?->status === 'diterima' ? 'Anda dapat melamar topik dosen di bank topik.' : 'Tersedia setelah topik mandiri diterima.' }}</p>
                @else
                    <ul class="divide-y divide-rule">
                        @foreach ($team->applications as $app)
                            <li class="px-4 py-3 sm:px-6">
                                <div class="flex items-start justify-between gap-3"><p class="font-medium">{{ $app->topic->title }}</p><x-badge :status="$app->status" /></div>
                                <p class="text-sm text-muted">{{ $app->topic->lecturer?->name }} · {{ $app->created_at->translatedFormat('j M Y') }}</p>
                                @if ($app->notes)<p class="mt-1 text-sm">{{ $app->notes }}</p>@endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    @endif
</x-layouts.app>
