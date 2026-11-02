{{-- Modal detail tim (Registrasi Tim admin, tombol "Detail tim"): nama tim, keputusan registrasi (Terima / Tolak dengan
     konfirmasi + catatan opsional untuk mahasiswa; tim yang sudah diputuskan hanya informasi), kartu Anggota (KHS),
     lalu hasil screening otomatis. Peminatan anggota dapat diisi/diubah langsung di kartu Anggota.
     Bagian "Ubah tim" & tombol "Hapus tim" = CRUD manual admin (mahasiswa tidak dapat mengubah timnya).
     Memakai state registrationForm dari elemen induk. Butuh $team (members.user), $courses, $specializations, $students, $teamSize. --}}
@php
    $card = 'rounded-lg border border-rule';
    $cardHead = 'border-b border-rule px-4 py-3 text-base font-semibold text-ink sm:px-5';
    $checks = collect($team->screening ?? []);
    $failed = $checks->where('passed', false)->count();
    // Ubah tim: nilai old() hanya untuk formulir tim yang terakhir dikirim (_team = id tim)
    $editing = $errors->any() && old('_team') === (string) $team->id;
    $current = $team->members->sortByDesc('is_leader')->pluck('user_id')->values();
    $choices = $team->members->pluck('user')->merge($students)->unique('id')->sortBy('name');
    $editable = $team->status !== 'ditolak';
@endphp
<x-modal width="sm:max-w-3xl" class="text-left font-normal">
    <x-slot:heading>{{ $team->name }}</x-slot:heading>
    <div class="space-y-5 text-left text-body">
        {{-- Keputusan registrasi: tombol Terima/Tolak untuk tim menunggu; tim yang sudah diputuskan cukup informasi. --}}
        @if ($team->status === 'menunggu')
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-rule bg-sunken px-4 py-3 sm:px-5">
                <p class="min-w-0 flex-1 text-sm text-muted">Diajukan {{ $team->submitted_at?->translatedFormat('j F Y H:i') ?? '—' }} · menunggu keputusan</p>
                <div class="flex gap-2">
                    <form method="POST" action="{{ route('admin.teams.decide', $team) }}"
                          data-confirm="Tolak registrasi {{ $team->name }}? Catatan di bawah akan dikirim ke anggota tim." data-confirm-label="Tolak" data-confirm-notes="notes">
                        @csrf<input type="hidden" name="decision" value="reject">
                        <button class="btn btn-secondary text-bad-ink hover:border-bad hover:bg-bad-tint hover:text-bad-ink">Tolak</button>
                    </form>
                    <form method="POST" action="{{ route('admin.teams.decide', $team) }}">
                        @csrf<input type="hidden" name="decision" value="approve">
                        <button class="btn btn-primary" data-once>Terima</button>
                    </form>
                </div>
            </div>
        @else
            @php $inactive = $team->members->filter(fn ($m) => $m->user->awaitingActivation())->count(); @endphp
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-rule bg-sunken px-4 py-3 sm:px-5">
                <p class="min-w-0 flex-1 text-sm text-muted">
                    Registrasi {{ $team->status === 'diterima' ? 'diterima' : ($team->status === 'ditolak' ? 'ditolak' : 'belum diajukan') }}{{ $team->reviewed_at ? ' '.$team->reviewed_at->translatedFormat('j F Y, H:i') : '' }}.
                    @if ($team->status === 'diterima' && $inactive) {{ $inactive }} anggota belum mengatur password.@endif
                </p>
                @if ($team->status === 'diterima')
                    {{-- Tetap tampil; nonaktif bila semua anggota sudah mengaktifkan akun --}}
                    <form method="POST" action="{{ route('admin.teams.activation', $team) }}">
                        @csrf
                        <button class="btn btn-secondary btn-sm disabled:cursor-not-allowed disabled:opacity-50" data-once @disabled(! $inactive)
                                @unless ($inactive) title="Semua anggota sudah mengaktifkan akun" @endunless>Kirim ulang tautan atur password</button>
                    </form>
                @endif
            </div>
        @endif
        @if ($team->review_notes)
            <p class="rounded-lg border border-rule px-4 py-3 text-sm sm:px-5"><span class="font-medium">Catatan admin:</span> {{ $team->review_notes }}</p>
        @endif

        {{-- CRUD manual admin: ubah nama/ketua/anggota (status tidak berubah) dan hapus tim --}}
        <div class="flex flex-wrap items-start gap-2">
            @if ($editable)
                <details class="group min-w-0 flex-1 rounded-lg border border-rule" @if ($editing) open @endif>
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-2.5 text-sm font-semibold text-ink sm:px-5 [&::-webkit-details-marker]:hidden">
                        <span class="flex items-center gap-2"><x-heroicon-o-pencil-square class="h-4 w-4" />Ubah tim</span>
                        <x-heroicon-o-chevron-down class="h-4 w-4 text-muted transition-transform group-open:rotate-180" />
                    </summary>
                    <form method="POST" action="{{ route('admin.teams.update', $team) }}" class="space-y-4 border-t border-rule px-4 py-4 sm:px-5">
                        @csrf @method('PUT')
                        <input type="hidden" name="_team" value="{{ $team->id }}">
                        <div>
                            <label class="field-label" for="edit-name-{{ $team->id }}">Nama tim<span class="text-bad-ink" aria-hidden="true"> *</span></label>
                            <input id="edit-name-{{ $team->id }}" name="name" class="input" value="{{ $editing ? old('name') : $team->name }}" required maxlength="100">
                            @if ($editing)@error('name')<p class="field-error">{{ $message }}</p>@enderror @endif
                        </div>
                        @for ($i = 0; $i < $teamSize['max']; $i++)
                            @php $selected = $editing ? old("members.$i") : $current->get($i); $required = $i < $teamSize['min']; @endphp
                            <div>
                                <label class="field-label" for="edit-member-{{ $team->id }}-{{ $i }}">{{ $i === 0 ? 'Ketua' : 'Anggota '.($i + 1) }}@if ($required)<span class="text-bad-ink" aria-hidden="true"> *</span>@else <span class="font-normal text-muted">(bila ada)</span>@endif</label>
                                <select id="edit-member-{{ $team->id }}-{{ $i }}" name="members[{{ $i }}]" class="input" @required($required)>
                                    <option value="">Pilih mahasiswa</option>
                                    @foreach ($choices as $student)
                                        <option value="{{ $student->id }}" @selected((string) $selected === (string) $student->id)>{{ $student->nim }} — {{ $student->name }}</option>
                                    @endforeach
                                </select>
                                @if ($editing)@error("members.$i")<p class="field-error">{{ $message }}</p>@enderror @endif
                            </div>
                        @endfor
                        @if ($editing)@error('members')<p class="field-error">{{ $message }}</p>@enderror @endif
                        <p class="field-hint">Pilihan: anggota tim ini dan mahasiswa aktif yang belum bertim. Anggota yang dikeluarkan beserta KHS-nya dihapus dari tim; status registrasi tidak berubah.</p>
                        <div class="flex justify-end"><button class="btn btn-primary btn-sm" data-once>Simpan perubahan</button></div>
                    </form>
                </details>
            @else
                <button type="button" class="btn btn-secondary btn-sm disabled:cursor-not-allowed disabled:opacity-50" disabled title="Tim yang ditolak sudah dibubarkan"><x-heroicon-o-pencil-square class="h-4 w-4" />Ubah tim</button>
            @endif
            <form method="POST" action="{{ route('admin.teams.destroy', $team) }}"
                  data-confirm="Hapus tim {{ $team->name }}? Seluruh data tim (anggota, topik, dokumen, log bimbingan, nilai) ikut terhapus dan tidak dapat dikembalikan. Akun mahasiswa tetap ada." data-confirm-label="Hapus tim">
                @csrf @method('DELETE')
                <button class="btn btn-secondary btn-sm text-bad-ink hover:border-bad hover:bg-bad-tint hover:text-bad-ink"><x-heroicon-o-trash class="h-4 w-4" />Hapus tim</button>
            </form>
        </div>

        {{-- Tim ditolak sudah dibubarkan (akun registrasi & KHS dihapus): hanya log data pendaftar --}}
        @if ($team->members->isEmpty() && $team->archived_members)
            <section class="{{ $card }}" aria-label="Pendaftar {{ $team->name }}">
                <h3 class="{{ $cardHead }}">Pendaftar <span class="text-sm font-normal text-muted">· log registrasi, tim sudah dibubarkan</span></h3>
                <ul class="divide-y divide-rule">
                    @foreach ($team->archived_members as $m)
                        <li class="px-4 py-4 sm:px-5">
                            <p class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold text-ink">{{ $m['name'] }}</span>
                                <span class="rounded-md px-1.5 py-0.5 text-caption font-medium {{ $m['is_leader'] ? 'bg-brand-tint text-brand-deep' : 'bg-sunken text-muted' }}">{{ $m['is_leader'] ? 'Ketua' : 'Anggota' }}</span>
                            </p>
                            <dl class="mt-2 grid grid-cols-[7rem_minmax(0,1fr)] gap-x-3 gap-y-1 text-sm">
                                <dt class="text-muted">NIM</dt><dd class="code text-ink">{{ $m['nim'] ?? '—' }}</dd>
                                <dt class="text-muted">Email</dt><dd class="min-w-0 break-all text-ink">{{ $m['email'] }}</dd>
                                <dt class="text-muted">SKS lulus</dt><dd class="tabular text-ink">{{ $m['sks_passed'] ?? '—' }}</dd>
                                @foreach ($m['grades'] ?? [] as $code => $grade)
                                    <dt class="text-muted">Nilai {{ $code }}</dt><dd class="text-ink">{{ $grade }}</dd>
                                @endforeach
                                <dt class="text-muted">Peminatan</dt><dd class="text-ink">{{ $m['specialization'] ?? '—' }}</dd>
                                <dt class="text-muted">Asal daerah</dt><dd class="text-ink">{{ $m['origin_region'] ?? '—' }}</dd>
                            </dl>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- Anggota --}}
        @if ($team->members->isNotEmpty())
        <section class="{{ $card }}" aria-label="Anggota {{ $team->name }}">
            <h3 class="{{ $cardHead }}">Anggota</h3>
            <ul class="divide-y divide-rule">
                @foreach ($team->members as $member)
                    @php $user = $member->user; @endphp
                    <li class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-start sm:px-5">
                        <div class="min-w-0 flex-1">
                            <p class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold text-ink">{{ $user->name }}</span>
                                <span class="rounded-md px-1.5 py-0.5 text-caption font-medium {{ $member->is_leader ? 'bg-brand-tint text-brand-deep' : 'bg-sunken text-muted' }}">{{ $member->is_leader ? 'Ketua' : 'Anggota' }}</span>
                                @if ($member->invite_status !== 'diterima')<x-badge :status="$member->invite_status" />@endif
                            </p>
                            <dl class="mt-2 grid grid-cols-[7rem_minmax(0,1fr)] gap-x-3 gap-y-1 text-sm">
                                <dt class="text-muted">NIM</dt><dd class="code text-ink">{{ $user->nim ?? '—' }}</dd>
                                <dt class="text-muted">Email</dt><dd class="min-w-0 break-all"><a class="link font-normal" href="mailto:{{ $user->email }}">{{ $user->email }}</a></dd>
                                <dt class="text-muted">SKS lulus</dt><dd class="tabular text-ink">{{ $user->sks_passed ?? '—' }}</dd>
                                @foreach ($courses as $course)
                                    <dt class="text-muted">Nilai {{ $course->code }}</dt><dd class="text-ink">{{ ($user->course_grades ?? [])[$course->code] ?? '—' }}</dd>
                                @endforeach
                                <dt class="text-muted">Asal daerah</dt><dd class="text-ink">{{ $user->origin_region ?? '—' }}</dd>
                                <dt class="text-muted">Akun</dt><dd class="text-ink">{{ $user->awaitingActivation() ? ($team->status === 'diterima' ? 'Belum mengatur password' : 'Aktif setelah tim diterima') : 'Aktif' }}</dd>
                                {{-- Peminatan dapat diisi admin di sini (dicocokkan lewat akun ber-NIM ini) bila mahasiswa belum punya saat registrasi --}}
                                <dt class="pt-2 text-muted"><label for="spec-{{ $team->id }}-{{ $user->id }}">Peminatan</label></dt>
                                <dd>
                                    <form method="POST" action="{{ route('admin.students.specialization', $user) }}" class="flex flex-wrap items-center gap-2">
                                        @csrf @method('PUT')
                                        <select id="spec-{{ $team->id }}-{{ $user->id }}" name="specialization" class="input min-h-[36px] w-auto max-w-full py-1 text-sm">
                                            <option value="">— Belum ada —</option>
                                            @foreach (collect($specializations)->push($user->specialization)->filter()->unique() as $spec)
                                                <option value="{{ $spec }}" @selected($user->specialization === $spec)>{{ $spec }}</option>
                                            @endforeach
                                        </select>
                                        <button class="btn btn-secondary btn-sm" data-once>Simpan<span class="sr-only"> peminatan {{ $user->name }}</span></button>
                                    </form>
                                </dd>
                            </dl>
                        </div>
                        @if ($member->khs_path)
                            <a href="{{ route('attachments.download', [$team, $member, 'khs']) }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm shrink-0"><x-heroicon-o-eye class="h-4 w-4" />Lihat KHS<span class="sr-only"> {{ $user->name }}</span></a>
                        @else
                            <button type="button" class="btn btn-secondary btn-sm shrink-0 disabled:cursor-not-allowed disabled:opacity-50" disabled title="KHS belum diunggah"><x-heroicon-o-eye class="h-4 w-4" />Lihat KHS</button>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
        @endif

        {{-- Hasil screening otomatis (dihitung dari persyaratan periode saat tim diajukan) --}}
        <section class="{{ $card }}" aria-label="Hasil screening {{ $team->name }}">
            <h3 class="{{ $cardHead }}">Hasil Screening</h3>
            <div class="px-4 py-4 sm:px-5">
                @if ($checks->isEmpty())
                    <p class="text-sm text-muted">Screening dijalankan saat tim mengajukan registrasi.</p>
                @else
                    <p class="text-sm font-medium {{ $failed ? 'text-bad-ink' : 'text-ok-ink' }}">{{ $failed ? "{$failed} syarat belum terpenuhi" : 'Semua syarat terpenuhi' }}</p>
                    <div class="mt-3">@include('admin.teams._screening-list')</div>
                @endif
            </div>
        </section>
    </div>
</x-modal>
