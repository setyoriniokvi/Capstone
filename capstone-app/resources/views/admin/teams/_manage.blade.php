{{-- Status registrasi (Terima / Tolak untuk tim menunggu; selain itu "Registrasi diterima …" + Kirim ulang tautan atur
     password), catatan admin, lalu Ubah tim (nama, ketua, anggota) | Hapus tim. Dipakai Detail tim Registrasi Tim, Riwayat
     Registrasi Tim (Master Data), dan Monitoring progres. Butuh $team (members.user), $students, $teamSize. --}}
@php
    // Ubah tim: nilai old() hanya untuk formulir tim yang terakhir dikirim (_team = id tim)
    $editing = $errors->any() && old('_team') === (string) $team->id;
    $current = $team->members->sortByDesc('is_leader')->pluck('user_id')->values();
    $choices = $team->members->pluck('user')->merge($students)->unique('id')->sortBy('name');
    $editable = $team->status !== 'ditolak';
@endphp
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
                                <option value="{{ $student->id }}" @selected((string) $selected === (string) $student->id)>{{ $student->nim }} - {{ $student->name }}</option>
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
