{{-- Kartu Anggota (admin): NIM, email, SKS, nilai MK prasyarat, asal daerah, status akun, peminatan (dapat diubah langsung),
     dan Lihat KHS. $inlineSpec = tombol Simpan peminatan selalu di samping dropdown (Monitoring progres).
     Butuh $team (members.user), $courses, $specializations; $card & $cardHead dari induk. --}}
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
                        <span class="inline-block whitespace-nowrap rounded-md px-1.5 py-0.5 text-caption font-medium {{ $member->is_leader ? 'bg-brand-tint text-brand-deep' : 'bg-sunken text-muted' }}">{{ $member->is_leader ? 'Ketua' : 'Anggota' }}</span>
                        @if ($member->invite_status !== 'diterima')<x-badge :status="$member->invite_status" />@endif
                    </p>
                    <dl class="mt-2 grid grid-cols-[7rem_minmax(0,1fr)] gap-x-3 gap-y-1 text-sm">
                        <dt class="text-muted">NIM</dt><dd class="code text-ink">{{ $user->nim ?? '—' }}</dd>
                        <dt class="text-muted">Email</dt><dd class="min-w-0 break-all"><a class="link font-normal" href="mailto:{{ $user->email }}">{{ $user->email }}</a></dd>
                        <dt class="text-muted">SKS lulus</dt><dd class="tabular text-ink">{{ $user->sks_passed ?? '—' }}</dd>
                        @foreach ($courses as $course)
                            <dt class="text-muted">Nilai {{ $course->code }}</dt><dd class="text-ink">{{ ($user->course_grades ?? [])[$course->code] ?? '—' }}</dd>
                        @endforeach
                        <dt class="text-muted">Asal daerah</dt><dd class="text-ink">{{ $user->origin_region ?? '-' }}@if (filled($user->origin_region) && ! \App\Support\Regions::isListed($user->origin_region))<span class="ml-1.5 inline-block rounded-md border border-warn-line bg-warn-tint px-1.5 py-0.5 text-caption font-medium text-warn-ink" title="Tidak ada di daftar kabupaten/kota; periksa ejaannya">diisi manual</span>@endif</dd>
                        <dt class="text-muted">Akun</dt><dd class="text-ink">{{ $user->awaitingActivation() ? ($team->status === 'diterima' ? 'Belum mengatur password' : 'Aktif setelah tim diterima') : 'Aktif' }}</dd>
                        {{-- Peminatan dapat diisi admin di sini (dicocokkan lewat akun ber-NIM ini) bila mahasiswa belum punya saat registrasi --}}
                        <dt class="pt-2 text-muted"><label for="spec-{{ $team->id }}-{{ $user->id }}">Peminatan</label></dt>
                        <dd>
                            <form method="POST" action="{{ route('admin.students.specialization', $user) }}" class="flex {{ ($inlineSpec ?? false) ? 'flex-nowrap' : 'flex-wrap' }} items-center gap-2">
                                @csrf @method('PUT')
                                <select id="spec-{{ $team->id }}-{{ $user->id }}" name="specialization" class="input min-h-[36px] py-1 text-sm {{ ($inlineSpec ?? false) ? 'w-auto min-w-0 flex-1' : 'w-auto max-w-full' }}">
                                    <option value="">— Belum ada —</option>
                                    @foreach (collect($specializations)->push($user->specialization)->filter()->unique() as $spec)
                                        <option value="{{ $spec }}" @selected($user->specialization === $spec)>{{ $spec }}</option>
                                    @endforeach
                                </select>
                                <button class="btn btn-secondary btn-sm shrink-0" data-once>Simpan<span class="sr-only"> peminatan {{ $user->name }}</span></button>
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
