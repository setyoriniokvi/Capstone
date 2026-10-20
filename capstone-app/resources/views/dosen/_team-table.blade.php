{{-- Tabel tim yang ditangani dosen: nama + judul topik lengkap, peran (bisa pembimbing & penguji), log disetujui.
     Hanya informasi — baris tidak dapat diklik, jadi tanpa efek hover. Butuh $teams, $minLogbooks. --}}
@if ($teams->isEmpty())
    <p class="border-t border-rule px-5 py-5 text-sm text-muted sm:px-6">Belum ada tim.</p>
@else
    <div class="table-wrap border-t border-rule">
        <table class="data-table [&_tbody_tr:hover_td]:bg-transparent">
            <thead class="bg-brand-tint"><tr class="[&>th]:border-0 [&>th]:py-3.5 [&>th]:text-sm [&>th]:font-semibold [&>th]:text-brand-deep"><th>Tim</th><th>Peran</th><th>Log disetujui</th></tr></thead>
            <tbody>
                @foreach ($teams as $team)
                    <tr class="[&>td]:py-4 [&>td]:align-top">
                        <td class="min-w-[18rem]">
                            <p class="font-semibold text-ink">{{ $team->name }}</p>
                            @if ($team->finalTopic)<p class="mt-0.5 whitespace-normal text-sm text-muted">{{ $team->finalTopic->title }}</p>@endif
                        </td>
                        <td>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($team->lecturers->pluck('role')->unique() as $role)
                                    <span class="inline-block whitespace-nowrap rounded-md border border-rule bg-sunken px-2.5 py-1 text-sm font-medium text-muted">{{ ucfirst($role) }}</span>
                                @endforeach
                            </div>
                        </td>
                        <td class="min-w-[11rem]">
                            <p class="tabular text-ink">{{ $team->logbook_approved }}/{{ $minLogbooks }}</p>
                            <x-progress-bar class="mt-1.5 w-44 max-w-full" :value="$team->logbook_approved" :max="$minLogbooks" label="Log disetujui {{ $team->name }}" />
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
