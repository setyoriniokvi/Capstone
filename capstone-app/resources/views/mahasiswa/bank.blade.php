@php $pendingApp = $applications->firstWhere('status', 'menunggu'); $acceptedApp = $applications->firstWhere('status', 'diterima'); @endphp
<x-layouts.app title="Bank Topik Dosen" section="Mahasiswa · Bank topik">
    <x-page-head title="Bank topik dosen" lede="Topik yang ditawarkan dosen dan sudah disetujui admin. Melamar bersifat opsional; satu lamaran aktif per tim." />

    @if ($acceptedApp)
        <p class="mb-6 rounded-lg border border-ok-line bg-ok-tint px-4 py-3 text-sm text-ok-ink">Tim Anda diterima di “{{ $acceptedApp->topic->title }}”. Topik ini menjadi topik final.</p>
    @elseif ($pendingApp)
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-warn-line bg-warn-tint px-4 py-3 text-sm text-warn-ink">
            <p>Lamaran ke “{{ $pendingApp->topic->title }}” sedang menunggu keputusan dosen.</p>
            <form method="POST" action="{{ route('mahasiswa.bank.withdraw', $pendingApp) }}">@csrf @method('DELETE')<button class="btn btn-secondary btn-sm">Batalkan lamaran</button></form>
        </div>
    @elseif (! $canApply)
        <p class="mb-6 rounded-lg border border-rule bg-sunken px-4 py-3 text-sm text-muted">Anda dapat melihat daftar topik. Melamar dibuka setelah registrasi tim dan topik mandiri diterima.</p>
    @endif

    <form method="GET" class="mb-5 flex flex-wrap items-end gap-3">
        <div class="min-w-0 flex-1 sm:max-w-sm"><label class="field-label" for="q">Cari topik atau dosen</label><input id="q" name="q" value="{{ request('q') }}" class="input" type="search"></div>
        <div><label class="field-label" for="bidang">Bidang</label>
            <select id="bidang" name="bidang" class="input"><option value="">Semua bidang</option>@foreach ($fields as $f)<option value="{{ $f }}" @selected(request('bidang') === $f)>{{ $f }}</option>@endforeach</select></div>
        <label class="flex min-h-[44px] items-center gap-2"><input type="checkbox" class="checkbox" name="tersedia" value="1" @checked(request()->boolean('tersedia'))>Masih ada kuota</label>
        <button class="btn btn-secondary">Cari</button>
    </form>

    @if ($topics->isEmpty())
        <div class="panel"><x-empty title="Tidak ada topik yang cocok" icon="book-open" /></div>
    @else
        <div class="columns-1 gap-4 md:columns-2 [&>*]:mb-4">
            @foreach ($topics as $topic)
                @php $remaining = max(0, $topic->quota - $topic->accepted_count); @endphp
                <article x-data="disclosure" class="panel break-inside-avoid">
                    <div class="px-4 pt-4 sm:px-5">
                        <div class="flex items-start justify-between gap-3">
                            <p class="text-sm text-muted">{{ $topic->field ?? 'Umum' }}</p>
                            <span class="whitespace-nowrap font-mono text-sm {{ $remaining ? 'text-ok-ink' : 'text-bad-ink' }}">{{ $remaining }}/{{ $topic->quota }} kuota</span>
                        </div>
                        <h2 class="mt-1 text-lg font-semibold leading-snug">{{ $topic->title }}</h2>
                        <p class="mt-2 line-clamp-4 whitespace-pre-line text-sm">{{ $topic->description }}</p>
                    </div>
                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-rule px-4 py-3 sm:px-5">
                        <div class="min-w-0 text-sm">
                            <p class="font-medium">{{ $topic->lecturer->name }}</p>
                            <p class="text-muted"><a class="link font-normal" href="mailto:{{ $topic->lecturer->email }}">{{ $topic->lecturer->email }}</a>@if ($topic->lecturer->phone) · <span class="code">{{ $topic->lecturer->phone }}</span>@endif</p>
                        </div>
                        @if ($canApply && ! $pendingApp && ! $acceptedApp)
                            <button type="button" class="btn btn-primary btn-sm" @click="toggle" @disabled($remaining === 0)>{{ $remaining ? 'Lamar' : 'Kuota penuh' }}</button>
                        @endif
                    </div>
                    @if ($canApply && $remaining)
                        <form x-cloak x-show="expanded" method="POST" action="{{ route('mahasiswa.bank.apply', $topic) }}" class="border-t border-rule bg-sunken px-4 py-3 sm:px-5">
                            @csrf
                            <label class="field-label" for="motivation-{{ $topic->id }}">Alasan memilih topik (opsional)</label>
                            <textarea id="motivation-{{ $topic->id }}" name="motivation" rows="3" class="input"></textarea>
                            <button class="btn btn-primary mt-3" data-once>Kirim lamaran</button>
                        </form>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
</x-layouts.app>
