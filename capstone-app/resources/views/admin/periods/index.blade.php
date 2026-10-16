<x-layouts.app title="Periode & Timeline" section="Admin · Periode">
    <x-page-head title="Periode & Timeline" lede="Setiap periode memiliki timeline 16 minggu, tenggat tahapan, jendela penilaian, dan folder Google Drive per prodi.">
        <x-slot:actions><a href="{{ route('admin.periods.create') }}" class="btn btn-primary"><x-heroicon-o-plus class="h-5 w-5" />Periode baru</a></x-slot:actions>
    </x-page-head>

    <div class="panel">
        @if ($periods->isEmpty())
            <x-empty title="Belum ada periode" icon="calendar-days">Buat periode pertama untuk memulai siklus Capstone.</x-empty>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Periode</th><th>Tahun akademik</th><th>Rentang</th><th>Tim</th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
                    <tbody>
                        @foreach ($periods as $period)
                            <tr>
                                <td class="font-medium">{{ $period->name }}</td>
                                <td class="code">{{ $period->academic_year }} · {{ ucfirst($period->semester) }}</td>
                                <td class="whitespace-nowrap">{{ $period->starts_on->translatedFormat('j M Y') }} – {{ $period->ends_on->translatedFormat('j M Y') }}</td>
                                <td class="font-mono">{{ $period->teams_count }}</td>
                                <td>@if ($period->is_active)<x-badge status="aktif" />@else<span class="text-sm text-muted">Tidak aktif</span>@endif</td>
                                <td>
                                    <div class="flex justify-end gap-2">
                                        @unless ($period->is_active)
                                            <form method="POST" action="{{ route('admin.periods.activate', $period) }}">@csrf<button class="btn btn-ghost btn-sm">Aktifkan</button></form>
                                        @endunless
                                        <a href="{{ route('admin.periods.edit', $period) }}" class="btn btn-secondary btn-sm">Kelola</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-layouts.app>
