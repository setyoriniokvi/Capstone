@php $total = $results->count(); @endphp
<x-layouts.app title="Rekap Nilai">
    <x-page-head title="Rekap nilai akhir" lede="Dihitung otomatis berdasarkan bobot CLO. Nilai CLO ditampilkan dalam persen capaian; total adalah jumlah poin dari 100.">
        <x-slot:actions>
            <a download href="{{ route($exportRoute, ['mode' => 'parsial']) }}" class="btn btn-secondary"><x-heroicon-o-arrow-down-tray class="h-5 w-5" />Ekspor parsial</a>
            @if ($exportRoute === 'admin.recap.export')
                <a download href="{{ route($exportRoute) }}" class="btn btn-primary {{ $complete < $total || $total === 0 ? 'pointer-events-none opacity-50' : '' }}" @if ($complete < $total || $total === 0) aria-disabled="true" title="Aktif bila semua tim lengkap" @endif><x-heroicon-o-arrow-down-tray class="h-5 w-5" />Ekspor penuh</a>
            @endif
        </x-slot:actions>
    </x-page-head>

    <div class="panel mb-6">
        <div class="panel-head">
            <p class="text-sm"><span class="tabular font-medium">{{ $complete }}</span><span class="text-muted"> dari {{ $total }} tim lengkap</span></p>
        </div>
        @if ($results->isEmpty())
            <x-empty title="Belum ada tim yang dinilai" icon="table-cells" />
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr><th>NIM</th><th>Nama</th><th>Tim</th>@foreach ($clos as $clo)<th class="text-right">{{ $clo->code }}</th>@endforeach<th class="text-right">Total</th><th>Grade</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($results as $result)
                            @foreach ($result['rows'] as $i => $row)
                                <tr>
                                    <td class="code">{{ $row['student']->nim }}</td>
                                    <td class="whitespace-nowrap">{{ $row['student']->name }}</td>
                                    <td class="whitespace-nowrap">
                                        <span class="code">{{ $result['team']->code }}</span>
                                        @if ($i === 0 && ! $result['complete'])
                                            <p class="text-caption text-warn-ink" title="Komponen belum lengkap">Belum: {{ collect($result['missing'])->map(fn ($c) => config("capstone.components.$c.label"))->implode(', ') }}</p>
                                        @endif
                                    </td>
                                    @foreach ($clos as $clo)
                                        <td class="text-right tabular {{ $result['complete'] ? '' : 'text-muted' }}">{{ $row['clos'][$clo->code]['points'] > 0 ? number_format($row['clos'][$clo->code]['percent'], 1, ',', '') : '—' }}</td>
                                    @endforeach
                                    <td class="text-right tabular font-medium">{{ $result['complete'] ? number_format($row['total'], 2, ',', '') : '—' }}</td>
                                    <td class="tabular font-medium">{{ $result['complete'] ? $row['grade'] : '—' }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
    <p class="text-caption text-muted">Skala grade mengikuti konfigurasi <span class="code">config/capstone.php → grade_scale</span>. Kolom ekspor mengikuti format rekap per mahasiswa; sesuaikan dengan template iGadis resmi bila berbeda.</p>
</x-layouts.app>
