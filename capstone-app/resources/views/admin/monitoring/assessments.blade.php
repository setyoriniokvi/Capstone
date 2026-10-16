<x-layouts.app title="Penilaian & Reminder" section="Admin · Monitoring penilaian">
    <x-page-head title="Monitoring penilaian" lede="Dosen dengan komponen nilai yang belum diisi. Kirim pengingat per dosen atau massal melalui email dan WhatsApp; setiap pengiriman tercatat." />

    @if (! $period)
        <div class="panel"><x-empty title="Belum ada periode aktif" icon="calendar-days" /></div>
    @else
        <div class="panel">
            <div class="panel-head">
                <x-filter-bar :action="route('admin.monitoring.assessments')">
                    <div><label class="field-label" for="prodi">Prodi dosen</label>
                        <select id="prodi" name="prodi" class="input"><option value="">Semua</option>@foreach ($prodis as $p)<option value="{{ $p->code }}" @selected(request('prodi') === $p->code)>{{ $p->code }}</option>@endforeach</select></div>
                    <div><label class="field-label" for="peran">Peran</label>
                        <select id="peran" name="peran" class="input"><option value="">Semua</option><option value="pembimbing" @selected(request('peran') === 'pembimbing')>Pembimbing</option><option value="penguji" @selected(request('peran') === 'penguji')>Penguji</option></select></div>
                    <div><label class="field-label" for="status">Status</label>
                        <select id="status" name="status" class="input"><option value="">Semua</option><option value="belum" @selected(request('status') === 'belum')>Belum lengkap</option><option value="lengkap" @selected(request('status') === 'lengkap')>Sudah lengkap</option></select></div>
                </x-filter-bar>
            </div>

            @if ($rows->isEmpty())
                <x-empty title="Belum ada penugasan dosen" icon="bell-alert">Plotting pembimbing dan penguji terlebih dahulu.</x-empty>
            @else
                <form method="POST" action="{{ route('admin.monitoring.remind') }}">
                    @csrf
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead><tr><th class="w-10"><span class="sr-only">Pilih</span></th><th>Dosen</th><th>Prodi</th><th>Tim ditangani</th><th>Komponen belum dinilai</th><th>Kontak</th><th>Reminder terakhir</th></tr></thead>
                            <tbody>
                                @foreach ($rows as $row)
                                    @php $l = $row['lecturer']; @endphp
                                    <tr>
                                        <td>@unless ($row['complete'])<input type="checkbox" class="checkbox" name="lecturer_ids[]" value="{{ $l->id }}" aria-label="Pilih {{ $l->name }}">@endunless</td>
                                        <td class="font-medium">{{ $l->name }}</td>
                                        <td>{{ $l->prodi_code ?? '—' }}</td>
                                        <td class="max-w-[14rem] text-sm"><span class="code">{{ $row['teams']->pluck('code')->implode(', ') }}</span></td>
                                        <td class="text-sm">
                                            @if ($row['complete'])
                                                <x-badge status="lengkap" label="Sudah lengkap" />
                                            @else
                                                @foreach ($row['missing'] as $componentKey => $count)
                                                    <p>{{ config('capstone.components.'.$componentKey.'.label') }} <span class="font-mono text-muted">({{ $count }} tim)</span></p>
                                                @endforeach
                                            @endif
                                        </td>
                                        <td class="text-sm"><p>{{ $l->email }}</p><p class="code {{ $l->phone ? 'text-muted' : 'text-bad-ink' }}">{{ $l->phone ?: 'WA kosong' }}</p></td>
                                        <td class="whitespace-nowrap text-sm text-muted">{{ isset($lastSent[$l->id]) ? \Illuminate\Support\Carbon::parse($lastSent[$l->id])->diffForHumans() : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-rule bg-sunken px-4 py-3 sm:px-5">
                        <fieldset class="flex flex-wrap items-center gap-4">
                            <legend class="sr-only">Kanal</legend>
                            <label class="flex min-h-[44px] items-center gap-2"><input type="checkbox" class="checkbox" name="channels[]" value="email" checked>Email</label>
                            <label class="flex min-h-[44px] items-center gap-2"><input type="checkbox" class="checkbox" name="channels[]" value="whatsapp" checked>WhatsApp</label>
                        </fieldset>
                        <button class="btn btn-primary" data-once><x-heroicon-o-paper-airplane class="h-5 w-5" />Kirim reminder ke dosen terpilih</button>
                    </div>
                </form>
            @endif
        </div>
    @endif
</x-layouts.app>
