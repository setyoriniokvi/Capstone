<x-layouts.app title="Dashboard">
    @if (! $period)
        <x-page-head title="Selamat datang di Capstone" lede="Mulai dengan membuat periode Capstone, lalu atur timeline, aturan registrasi, dan impor data dosen serta mahasiswa." />
        <div class="panel">
            <x-empty title="Belum ada periode aktif" icon="calendar-days">
                <a href="{{ route('admin.periods.create') }}" class="btn btn-primary mt-4">Buat periode</a>
            </x-empty>
        </div>
    @else
        {{-- Sambutan (sama dengan dashboard mahasiswa & dosen) + minggu berjalan --}}
        @php $week = $period->currentWeek(); @endphp
        <section class="hero-panel mb-6 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between" aria-labelledby="sambutan">
            <div class="min-w-0">
                <p class="text-sm text-on-brand-muted"><time datetime="{{ now()->toDateString() }}">{{ now()->translatedFormat('l, j F Y') }}</time></p>
                <h1 id="sambutan" class="mt-1 text-h1 font-semibold">Selamat datang, Admin.</h1>
                <p class="mt-2 max-w-[60ch] text-[0.9375rem] text-on-brand-muted">{{ $period->name }} · Tahun akademik {{ $period->academic_year }} · semester {{ $period->semester }}</p>
            </div>
            <p class="shrink-0 border-t border-on-brand-line pt-4 sm:border-l sm:border-t-0 sm:pl-6 sm:pt-0 sm:text-right">
                @if ($week)
                    <span class="block text-sm text-on-brand-muted">Minggu ke</span>
                    <span class="tabular text-[2rem] font-semibold leading-none tracking-tight">{{ $week }}</span><span class="tabular text-on-brand-muted"> / {{ $period->displayWeeks() }}</span>
                @else
                    <span class="block text-sm text-on-brand-muted">Periode</span>
                    <span class="font-semibold">{{ $period->name }}</span>
                @endif
            </p>
        </section>

        <x-capstone-timeline class="mb-6" :period="$period" with-assessments :bold-assessments="false" />

        <x-todo-card class="mb-6" :actions="$todo" />

        <div class="grid gap-6 md:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
            {{-- Registrasi Tim + status mahasiswa (bar bertumpuk, tooltip saat disorot) --}}
            <section class="panel divide-y divide-rule self-start" aria-label="Registrasi tim dan status mahasiswa">
                <x-stacked-bar title="Registrasi Tim" :total="$registration['teamTotal'].' tim'" :href="route('admin.teams.index')" :counts="$registration['teams']"
                               :items="['diterima' => ['Diterima', 'bg-brand'], 'menunggu' => ['Menunggu', 'bg-warn'], 'ditolak' => ['Ditolak', 'bg-bad']]" />
                <x-stacked-bar title="Status Mahasiswa" :total="$registration['studentTotal'].' mahasiswa'" :href="route('admin.students.index')" :counts="$registration['students']"
                               :items="['tergabung' => ['Tergabung tim', 'bg-brand'], 'menunggu' => ['Menunggu approval', 'bg-warn'], 'belum' => ['Belum ada tim', 'bg-rule-strong']]" />
            </section>

            {{-- Status Dosen: peran dosen pada tim periode ini --}}
            <section class="panel self-start" aria-label="Status dosen">
                <x-stacked-bar title="Status Dosen" :total="$lecturers['total'].' dosen'" :href="route('admin.lecturers.index')" :counts="$lecturers['counts']"
                               :items="['keduanya' => ['Pembimbing dan Penguji', 'bg-brand'], 'pembimbing' => ['Pembimbing saja', 'bg-accent'], 'penguji' => ['Penguji saja', 'bg-ink'], 'belum' => ['Belum ada role', 'bg-rule-strong']]" />
            </section>
        </div>
    @endif
</x-layouts.app>
