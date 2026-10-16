<x-layouts.app title="Notifikasi" section="Notifikasi">
    <x-page-head title="Notifikasi" lede="Perubahan status registrasi, topik, approval, revisi, dan tenggat.">
        <x-slot:actions>
            <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="btn btn-secondary">Tandai semua dibaca</button></form>
        </x-slot:actions>
    </x-page-head>
    <div class="panel">
        @if ($notifications->isEmpty())
            <x-empty title="Belum ada notifikasi" icon="bell" />
        @else
            <ul class="divide-y divide-rule">
                @foreach ($notifications as $n)
                    <li>
                        <a href="{{ route('notifications.open', $n->id) }}" class="flex gap-3 px-4 py-3 hover:bg-brand-wash sm:px-5">
                            <span class="mt-2 h-2 w-2 shrink-0 rounded-full {{ $n->read_at ? 'bg-transparent' : 'bg-brand' }}" aria-hidden="true"></span>
                            <span class="min-w-0 flex-1">
                                <span class="block {{ $n->read_at ? 'text-ink' : 'font-semibold' }}">{{ $n->data['title'] ?? 'Notifikasi' }}</span>
                                <span class="block text-sm text-muted">{{ $n->data['body'] ?? '' }}</span>
                            </span>
                            <time class="shrink-0 text-caption text-muted" datetime="{{ $n->created_at->toIso8601String() }}">{{ $n->created_at->diffForHumans() }}</time>
                        </a>
                    </li>
                @endforeach
            </ul>
            {{ $notifications->links() }}
        @endif
    </div>
</x-layouts.app>
