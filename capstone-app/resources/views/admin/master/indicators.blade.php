<x-layouts.app title="Indikator Penilaian" section="Admin · Master penilaian">
    <x-page-head title="Indikator penilaian" lede="Nilai indikator = (Skor ÷ 5) × Bobot × Faktor Pemahaman. Faktor pemahaman hanya berlaku pada skema Lisan dan Dokumen + Lisan.">
        <x-slot:actions><a href="{{ route('admin.indicators.create') }}" class="btn btn-primary"><x-heroicon-o-plus class="h-5 w-5" />Indikator baru</a></x-slot:actions>
    </x-page-head>
    @include('admin.master._weights-summary')

    @forelse ($clos as $clo)
        <section class="panel mb-4">
            <div class="panel-head">
                <h2 class="panel-title"><span class="code mr-2 text-brand-deep">{{ $clo->code }}</span><span class="font-normal">{{ \Illuminate\Support\Str::limit($clo->description, 90) }}</span></h2>
                <a href="{{ route('admin.indicators.create', ['clo' => $clo->id]) }}" class="btn btn-ghost btn-sm">Tambah</a>
            </div>
            @if ($clo->indicators->isEmpty())
                <p class="px-4 py-4 text-sm text-muted sm:px-5">Belum ada indikator untuk {{ $clo->code }}.</p>
            @else
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>ID</th><th>Indikator</th><th>Bukti / bab acuan</th><th>Skema</th><th>Pembagian bobot</th><th class="text-right">Bobot</th><th>Rubrik</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($clo->indicators as $ind)
                                <tr>
                                    <td class="code font-medium">{{ $ind->code }}</td>
                                    <td class="max-w-[20rem]">{{ $ind->name }}</td>
                                    <td class="max-w-[12rem] text-sm text-muted">{{ $ind->evidence ?? '—' }}</td>
                                    <td class="whitespace-nowrap text-sm">{{ $ind->schemeLabel() }}</td>
                                    <td class="text-caption">@foreach ($ind->componentWeights as $w)<p>{{ config('capstone.components.'.$w->component.'.label') }} <span class="font-mono">{{ $w->weight + 0 }}</span></p>@endforeach</td>
                                    <td class="text-right font-mono font-medium">{{ $ind->weight + 0 }}</td>
                                    <td>@if ($ind->rubric)<x-badge status="lengkap" label="Ada" />@else<a class="link text-sm" href="{{ route('admin.rubrics.edit', $ind) }}">Isi</a>@endif</td>
                                    <td><div class="flex justify-end gap-1"><a class="btn btn-ghost btn-sm" href="{{ route('admin.indicators.edit', $ind) }}">Ubah</a>
                                        <form method="POST" action="{{ route('admin.indicators.destroy', $ind) }}">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm text-bad-ink">Hapus</button></form></div></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @empty
        <div class="panel"><x-empty title="Belum ada CLO" icon="squares-2x2">Isi <a class="link" href="{{ route('admin.clos.index') }}">resume CLO</a> terlebih dahulu.</x-empty></div>
    @endforelse
</x-layouts.app>
