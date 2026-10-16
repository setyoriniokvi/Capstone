<x-layouts.app title="Topik Saya" section="Dosen · Topik">
    <x-page-head title="Topik dosen" lede="Topik terbit di bank topik setelah disetujui admin. Mengubah judul, deskripsi, atau bidang mengirim ulang topik untuk ditinjau; mengubah kuota saja tidak.">
        @if ($canPropose)
            <x-slot:actions><a href="{{ route('dosen.topics.create') }}" class="btn btn-primary"><x-heroicon-o-plus class="h-5 w-5" />Tambah topik</a></x-slot:actions>
        @endif
    </x-page-head>
    @unless ($canPropose)
        <p class="mb-6 rounded-lg border border-rule bg-sunken px-4 py-3 text-sm text-muted">Akun Anda belum memiliki hak akses sebagai pengusul topik. Hubungi admin bila diperlukan.</p>
    @endunless
    <div class="space-y-4">
        @forelse ($topics as $topic)
            <article class="panel">
                <div class="flex flex-wrap items-start gap-3 px-4 pt-4 sm:px-5">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-muted">{{ $topic->field }}</p>
                        <h2 class="font-semibold">{{ $topic->title }}</h2>
                    </div>
                    <x-badge :status="$topic->status" :label="$topic->status === 'diterima' ? 'Terbit di bank topik' : null" />
                </div>
                <p class="mt-2 line-clamp-3 px-4 text-sm sm:px-5">{{ $topic->description }}</p>
                @if ($topic->review_notes)<p class="mx-4 mt-3 rounded border border-rule bg-sunken px-3 py-2 text-sm sm:mx-5"><span class="font-medium">Catatan admin:</span> {{ $topic->review_notes }}</p>@endif
                <div class="mt-4 flex flex-wrap items-center gap-x-6 gap-y-2 border-t border-rule px-4 py-3 text-sm sm:px-5">
                    <span>Kuota <span class="font-mono font-medium">{{ $topic->accepted_count }}/{{ $topic->quota }}</span></span>
                    <span>Pelamar menunggu <span class="font-mono font-medium">{{ $topic->pending_count }}</span></span>
                    @if ($topic->applications->isNotEmpty())<span class="text-muted">Tim: <span class="code">{{ $topic->applications->pluck('team.code')->implode(', ') }}</span></span>@endif
                    <span class="flex-1"></span>
                    <a href="{{ route('dosen.topics.edit', $topic) }}" class="btn btn-ghost btn-sm">Ubah</a>
                    @if ($topic->accepted_count === 0 && $topic->pending_count === 0)
                        <form method="POST" action="{{ route('dosen.topics.destroy', $topic) }}">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm text-bad-ink">Hapus</button></form>
                    @endif
                </div>
            </article>
        @empty
            <div class="panel"><x-empty title="Belum ada topik" icon="light-bulb" /></div>
        @endforelse
    </div>
</x-layouts.app>
