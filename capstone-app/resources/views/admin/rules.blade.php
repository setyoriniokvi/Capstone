<x-layouts.app title="Aturan Registrasi" section="Admin · Aturan registrasi">
    <x-page-head title="Aturan registrasi tim" lede="Syarat ini menjadi dasar screening otomatis: minimal SKS, mata kuliah prasyarat atau micro-course, daerah asal berbeda, dan peminatan berbeda." />

    @if (! $period)
        <div class="panel"><x-empty title="Belum ada periode aktif" icon="calendar-days">Aturan registrasi berlaku per periode.</x-empty></div>
    @else
        <div class="space-y-6">
            @foreach ($prodis as $prodi)
                @php $rule = $rules->get($prodi->code); @endphp
                <section class="panel">
                    <div class="panel-head">
                        <h2 class="panel-title"><span class="code mr-2 text-muted">{{ $prodi->code }}</span>{{ $prodi->name }}</h2>
                        @unless ($rule)<x-badge status="belum diatur" />@endunless
                    </div>
                    <div class="grid gap-6 panel-body md:grid-cols-2">
                        <form method="POST" action="{{ route('admin.rules.update', $prodi->code) }}" class="space-y-4">
                            @csrf @method('PUT')
                            <div class="grid grid-cols-3 gap-3">
                                <div>
                                    <label class="field-label" for="sks-{{ $prodi->code }}">Min. SKS</label>
                                    <input id="sks-{{ $prodi->code }}" type="number" name="min_sks" min="0" max="160" class="input font-mono" value="{{ $rule->min_sks ?? 110 }}" required>
                                </div>
                                <div>
                                    <label class="field-label" for="min-{{ $prodi->code }}">Min. anggota</label>
                                    <input id="min-{{ $prodi->code }}" type="number" name="min_members" min="1" max="10" class="input font-mono" value="{{ $rule->min_members ?? config('capstone.team_size.min') }}" required>
                                </div>
                                <div>
                                    <label class="field-label" for="max-{{ $prodi->code }}">Maks. anggota</label>
                                    <input id="max-{{ $prodi->code }}" type="number" name="max_members" min="1" max="10" class="input font-mono" value="{{ $rule->max_members ?? config('capstone.team_size.max') }}" required>
                                </div>
                            </div>
                            <label class="flex min-h-[44px] items-center gap-3">
                                <input type="checkbox" class="checkbox" name="require_distinct_region" value="1" @checked($rule->require_distinct_region ?? true)>
                                <span>Wajib minimal satu anggota berbeda daerah asal <span class="text-muted">(multikultural)</span></span>
                            </label>
                            <label class="flex min-h-[44px] items-center gap-3">
                                <input type="checkbox" class="checkbox" name="require_distinct_specialization" value="1" @checked($rule->require_distinct_specialization ?? true)>
                                <span>Wajib minimal satu anggota berbeda peminatan <span class="text-muted">(multidisiplin)</span></span>
                            </label>
                            <button class="btn btn-primary">Simpan aturan {{ $prodi->code }}</button>
                        </form>

                        <div>
                            <h3 class="text-sm font-semibold">Mata kuliah prasyarat / micro-course</h3>
                            @if ($rule)
                                <ul class="mt-2 divide-y divide-rule rounded border border-rule">
                                    @forelse ($rule->courses as $course)
                                        <li class="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                                            <span><span class="code mr-2">{{ $course->code }}</span>{{ $course->name }}</span>
                                            <form method="POST" action="{{ route('admin.rules.courses.destroy', $course) }}">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm text-bad-ink" aria-label="Hapus {{ $course->code }}">Hapus</button></form>
                                        </li>
                                    @empty
                                        <li class="px-3 py-3 text-sm text-muted">Belum ada mata kuliah prasyarat.</li>
                                    @endforelse
                                </ul>
                                <form method="POST" action="{{ route('admin.rules.courses.store', $rule) }}" class="mt-3 grid grid-cols-[minmax(0,7rem)_minmax(0,1fr)_auto] gap-2">
                                    @csrf
                                    <label class="sr-only" for="c-code-{{ $prodi->code }}">Kode</label>
                                    <input id="c-code-{{ $prodi->code }}" name="code" class="input font-mono" placeholder="Kode" required>
                                    <label class="sr-only" for="c-name-{{ $prodi->code }}">Nama mata kuliah</label>
                                    <input id="c-name-{{ $prodi->code }}" name="name" class="input" placeholder="Nama mata kuliah" required>
                                    <button class="btn btn-secondary">Tambah</button>
                                </form>
                                <p class="field-hint">Kode dicocokkan dengan kolom <span class="code">mk_lulus</span> pada data mahasiswa.</p>
                            @else
                                <p class="mt-2 text-sm text-muted">Simpan aturan terlebih dahulu untuk menambahkan mata kuliah.</p>
                            @endif
                        </div>
                    </div>
                </section>
            @endforeach
        </div>
    @endif
</x-layouts.app>
