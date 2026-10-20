<x-layouts.app title="Ringkasan" section="Admin">
    @if (! $period)
        <x-page-head title="Selamat datang di Capstone" lede="Mulai dengan membuat periode Capstone, lalu atur timeline, aturan registrasi, dan impor data dosen serta mahasiswa." />
        <div class="panel">
            <x-empty title="Belum ada periode aktif" icon="calendar-days">
                <a href="{{ route('admin.periods.create') }}" class="btn btn-primary mt-4">Buat periode</a>
            </x-empty>
        </div>
    @else
        <x-page-head :title="$period->name" :lede="'Tahun akademik '.$period->academic_year.' · semester '.$period->semester.' · '.$period->starts_on->translatedFormat('j M').' – '.$period->ends_on->translatedFormat('j M Y')">
            <x-slot:actions>
                <a href="{{ route('admin.periods.edit', $period) }}" class="btn btn-secondary">Atur timeline</a>
            </x-slot:actions>
        </x-page-head>

        <x-capstone-timeline class="mb-6" :period="$period" :edit-href="route('admin.periods.edit', $period)" />

        <div class="grid gap-6 md:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
            <section class="panel">
                <div class="panel-head"><h2 class="panel-title">Perlu tindakan</h2></div>
                <ul class="divide-y divide-rule">
                    @foreach ($actions as $action)
                        <li>
                            <a href="{{ $action['route'] }}" class="flex min-h-[52px] items-center justify-between gap-4 px-4 hover:bg-brand-wash sm:px-6">
                                <span class="{{ $action['count'] ? 'text-ink' : 'text-muted' }}">{{ $action['label'] }}</span>
                                <span class="tabular text-lg {{ $action['count'] ? 'font-medium text-ink' : 'text-faint' }}">{{ $action['count'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>

            <div class="space-y-6">
                <section class="panel">
                    <div class="panel-head"><h2 class="panel-title">Registrasi tim</h2><span class="tabular text-sm text-muted">{{ $teamTotal }} tim</span></div>
                    <div class="panel-body space-y-3">
                        @php $statuses = ['diterima' => 'bg-brand', 'menunggu' => 'bg-warn', 'draft' => 'bg-rule-strong', 'ditolak' => 'bg-bad']; @endphp
                        <div class="flex h-3 overflow-hidden rounded-sm bg-sunken" role="img" aria-label="Distribusi status registrasi tim">
                            @foreach ($statuses as $status => $color)
                                @if (($statusCounts[$status] ?? 0) > 0)
                                    <span class="{{ $color }}" style="width: {{ $statusCounts[$status] / max(1, $teamTotal) * 100 }}%"></span>
                                @endif
                            @endforeach
                        </div>
                        <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                            @foreach ($statuses as $status => $color)
                                <div class="flex items-center justify-between gap-2">
                                    <dt class="flex items-center gap-2 text-muted"><span class="h-2 w-2 rounded-sm {{ $color }}"></span>{{ ucfirst($status) }}</dt>
                                    <dd class="tabular">{{ $statusCounts[$status] ?? 0 }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                </section>

                <section class="panel">
                    <div class="panel-head"><h2 class="panel-title">Aktivitas dosen</h2></div>
                    <dl class="kv panel-body">
                        <dt>Log bimbingan menunggu</dt><dd class="tabular">{{ $pipeline['logbook_pending'] }}</dd>
                        <dt>Lamaran topik menunggu</dt><dd class="tabular">{{ $pipeline['applications_pending'] }}</dd>
                        <dt>Komponen nilai terisi</dt>
                        <dd><span class="tabular">{{ $pipeline['assessment_done'] }}</span><span class="text-muted"> / {{ $pipeline['assessment_total'] }}</span>
                            <a href="{{ route('admin.monitoring.assessments') }}" class="link ml-2 text-sm">Pantau</a></dd>
                    </dl>
                </section>
            </div>
        </div>
    @endif
</x-layouts.app>
