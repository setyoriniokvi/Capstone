<x-layouts.app title="Penilaian" section="Dosen · Penilaian">
    <x-page-head title="Penilaian berbasis rubrik" lede="Sebagai pembimbing: proposal, laporan progress, serta laporan & presentasi akhir. Sebagai penguji: laporan & presentasi akhir serta pameran." />

    <nav class="mb-4 flex flex-wrap gap-2">
        @foreach (['' => 'Semua', 'bimbingan' => 'Tim bimbingan', 'uji' => 'Tim uji'] as $k => $l)
            <a href="{{ route('dosen.assessments.index', array_filter(['kategori' => $k])) }}" class="btn btn-sm {{ (string) $category === $k ? 'btn-primary' : 'btn-secondary' }}">{{ $l }}</a>
        @endforeach
    </nav>

    <div class="panel">
        @if ($rows->isEmpty())
            <x-empty title="Belum ada tim untuk dinilai" icon="pencil-square">Tim muncul setelah admin melakukan plotting.</x-empty>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Tim</th><th>Komponen</th><th>Peran</th><th>Jendela penilaian</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php $w = $windows->get($row['component']); $isDraft = $drafts->has($row['team']->id.':'.$row['component']); @endphp
                            <tr>
                                <td><span class="code font-medium">{{ $row['team']->code }}</span><p class="text-sm">{{ $row['team']->name }}</p></td>
                                <td>{{ config('capstone.components.'.$row['component'].'.label') }}</td>
                                <td class="capitalize text-muted">{{ $row['role'] }}</td>
                                <td class="whitespace-nowrap text-sm">
                                    @if ($w)
                                        {{ $w->opens_at->translatedFormat('j M') }} – {{ $w->due_at->translatedFormat('j M H:i') }}
                                        <p class="text-caption {{ $w->isOpen() ? 'text-ok-ink' : 'text-muted' }}">{{ $w->isOpen() ? 'dibuka' : (now()->lt($w->opens_at) ? 'belum dibuka' : 'ditutup') }}</p>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>@if ($row['done'])<x-badge status="final" label="Final" />@elseif ($isDraft)<x-badge status="draft" label="Draf" />@else<x-badge status="belum" label="Belum dinilai" />@endif</td>
                                <td class="text-right"><a href="{{ route('dosen.assessments.edit', [$row['team'], $row['component']]) }}" class="btn btn-sm {{ $row['done'] ? 'btn-ghost' : 'btn-secondary' }}">{{ $row['done'] ? 'Lihat / ubah' : 'Nilai' }}</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-layouts.app>
