<x-layouts.app title="Rubrik Dokumen" section="Admin · Master penilaian">
    <x-page-head title="Rubrik penilaian dokumen" lede="Deskripsi tiap tingkat skor per indikator: 5 Sangat Baik (95), 4 Baik (80), 3 Cukup (70), 2 Kurang (50), 1 Sangat Kurang (40), 0 Tidak Mengumpulkan (0)." />
    <div class="panel">
        @if ($indicators->isEmpty())
            <x-empty title="Belum ada indikator" icon="list-bullet" />
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Indikator</th><th>CLO</th><th>Bobot</th><th>Poin penilaian</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($indicators as $ind)
                            <tr>
                                <td><span class="code font-medium">{{ $ind->code }}</span><p class="max-w-[22rem] text-sm">{{ $ind->name }}</p></td>
                                <td class="code">{{ $ind->clo->code }}</td>
                                <td class="tabular">{{ $ind->weight + 0 }}</td>
                                <td class="max-w-[22rem] text-sm text-muted">{{ \Illuminate\Support\Str::limit($ind->rubric?->assessment_points, 120) ?: '—' }}</td>
                                <td>@if ($ind->rubric)<x-badge status="lengkap" label="Lengkap" />@else<x-badge status="belum" label="Belum ada" />@endif</td>
                                <td class="text-right"><a href="{{ route('admin.rubrics.edit', $ind) }}" class="btn btn-secondary btn-sm">{{ $ind->rubric ? 'Ubah' : 'Isi rubrik' }}</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-layouts.app>
