{{-- F11 — Monitoring progres tim (admin), hanya tim yang registrasinya diterima. Kartu filter: cari nama tim / nama
     anggota / topik final + dropdown peminatan anggota (langsung menyaring). Tabel: Nama tim, Topik final, Status topik
     (dosen/mandiri), Dosen pembimbing, Dosen penguji, Log bimbingan, lalu "Detail tim" (modal admin/monitoring/_detail:
     anggota, dokumen per milestone, rincian log bimbingan). --}}
@php
    // Judul kolom hijau & sel selebar kartu (sama dengan Approval Registrasi Tim)
    $greenHead = 'bg-brand-tint [&>tr>th]:border-0 [&>tr>th]:py-3.5 [&>tr>th]:text-sm [&>tr>th]:font-semibold [&>tr>th]:text-brand-deep';
    $cells = '[&_td]:break-words [&_td]:px-3 [&_th]:px-3 [&_td:first-child]:pl-5 [&_th:first-child]:pl-5 [&_td:last-child]:pr-5 [&_th:last-child]:pr-5 sm:[&_td:first-child]:pl-6 sm:[&_th:first-child]:pl-6 sm:[&_td:last-child]:pr-6 sm:[&_th:last-child]:pr-6';
    $chip = 'inline-block whitespace-nowrap rounded-md px-2 py-0.5 text-caption font-medium';
    $filtered = request()->filled('q') || $peminatan !== '';
@endphp
<x-layouts.app title="Progres Tim">
    <x-page-head title="Monitoring progres" lede="Topik final, dosen, dan log bimbingan setiap tim yang registrasinya diterima." />

    @if (! $period)
        <div class="panel"><x-empty title="Belum ada periode aktif" icon="calendar-days" /></div>
    @else
        <div class="panel mb-6">
            <div class="panel-head">
                <x-filter-bar :action="route('admin.monitoring.progress')">
                    <div class="min-w-[12rem] flex-1"><label class="field-label" for="q">Cari</label>
                        <input id="q" name="q" value="{{ request('q') }}" class="input" placeholder="Nama tim, nama anggota, topik final"></div>
                    <div><label class="field-label" for="peminatan">Peminatan</label>
                        <select id="peminatan" name="peminatan" class="input sm:w-64">
                            <option value="">Semua peminatan</option>
                            @foreach ($specializations as $spec)<option value="{{ $spec }}" @selected($peminatan === $spec)>{{ $spec }}</option>@endforeach
                        </select></div>
                </x-filter-bar>
            </div>
        </div>

        <section class="panel overflow-hidden" aria-labelledby="progres-judul">
            <div class="flex items-center justify-between gap-3 px-5 py-4 sm:px-6">
                <h2 id="progres-judul" class="text-lg font-semibold text-ink">Progres Tim</h2>
                <span class="text-sm text-muted">Jumlah tim: <span class="tabular font-medium text-ink">{{ $teams->total() }}</span></span>
            </div>
            @if ($teams->isEmpty())
                <div class="border-t border-rule"><x-empty :title="$filtered ? 'Tidak ada tim yang cocok' : 'Belum ada tim yang diterima'" icon="user-group" /></div>
            @else
                <div class="overflow-x-auto border-t border-rule">
                    <table class="data-table w-full min-w-[64rem] {{ $cells }}">
                        <thead class="{{ $greenHead }}"><tr>
                            <th>Nama tim</th><th>Topik final</th><th>Status topik</th><th>Dosen pembimbing</th><th>Dosen penguji</th><th>Log bimbingan</th><th><span class="sr-only">Detail</span></th>
                        </tr></thead>
                        <tbody>
                            @foreach ($teams as $team)
                                @php $topic = $team->finalTopic; @endphp
                                <tr class="[&>td]:py-3.5 [&>td]:align-middle">
                                    <td>
                                        <p class="font-medium text-ink">{{ $team->name }}</p>
                                        <p class="text-caption text-muted">{{ $team->members->count() }} anggota</p>
                                    </td>
                                    <td class="max-w-[16rem] text-sm text-ink">{{ $topic?->title ?? '—' }}</td>
                                    <td>
                                        @if ($topic)
                                            <span class="{{ $chip }} {{ $topic->source === 'dosen' ? 'bg-brand-tint text-brand-deep' : 'border border-rule bg-sunken text-muted' }}">{{ $topic->source === 'dosen' ? 'Topik dosen' : 'Topik mandiri' }}</span>
                                        @else
                                            <span class="text-sm text-faint">Belum ada</span>
                                        @endif
                                    </td>
                                    @foreach ([$team->supervisor, $team->examiner] as $assigned)
                                        <td class="text-sm">
                                            @if ($assigned)
                                                <p class="text-ink">{{ $assigned->lecturer->name }}</p>
                                                @if ($assigned->lecturer->lecturerProfile?->lecturer_code)<p class="code text-caption text-muted">{{ $assigned->lecturer->lecturerProfile->lecturer_code }}</p>@endif
                                            @else
                                                <span class="text-faint">Belum diplot</span>
                                            @endif
                                        </td>
                                    @endforeach
                                    <td class="min-w-[12rem]">
                                        <x-gate-cells :approved="$team->logbook_approved" :min="$minLogbooks" :pending="$team->logbook_pending" class="[&_.h-6]:h-2.5 [&_p]:mt-1.5 [&_p]:text-caption" />
                                        @if ($team->logbook_revision)<p class="mt-0.5 text-caption text-bad-ink">{{ $team->logbook_revision }} perlu revisi</p>@endif
                                    </td>
                                    <td class="text-right">
                                        <div x-data="registrationForm" data-open="false">
                                            <button type="button" class="btn btn-secondary btn-sm whitespace-nowrap" @click="open" :aria-expanded="expanded">Detail tim<span class="sr-only"> {{ $team->name }}</span></button>
                                            @include('admin.monitoring._detail')
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($teams->hasPages())<div class="border-t border-rule px-5 py-3 sm:px-6">{{ $teams->links() }}</div>@endif
            @endif
        </section>
    @endif
</x-layouts.app>
