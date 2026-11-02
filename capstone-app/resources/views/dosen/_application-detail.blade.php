{{-- Isi modal "Detail tim" di Approval Pengajuan Topik: ringkasan pengajuan (topik, waktu pengajuan, portofolio) lalu
     satu kartu per anggota — nama & NIM (kanan atas: chip Ketua/Anggota), asal daerah, peminatan, SKS lulus,
     indeks nilai mata kuliah prasyarat, dan tautan KHS.
     Butuh $app (TopicApplication dengan team.members.user & topic), $courses, $portfolioHref. --}}
<dl class="grid gap-4 rounded-lg border border-rule bg-sunken px-4 py-3 text-sm sm:grid-cols-2">
    <div class="sm:col-span-2">
        <dt class="text-muted">Topik yang diajukan</dt>
        <dd class="mt-0.5 font-medium text-ink">{{ $app->topic->title }}</dd>
    </div>
    <div>
        <dt class="text-muted">Waktu pengajuan</dt>
        <dd class="mt-0.5 text-ink">{{ $app->created_at->translatedFormat('j M Y, H:i') }}</dd>
    </div>
    <div>
        <dt class="text-muted">Portofolio</dt>
        <dd class="mt-0.5">
            @if ($app->portfolio_url)
                <a href="{{ $portfolioHref($app->portfolio_url) }}" target="_blank" rel="noopener noreferrer" class="link">Buka portofolio</a>
            @else
                <span class="text-muted">—</span>
            @endif
        </dd>
    </div>
</dl>

<ul class="mt-5 space-y-4">
    @foreach ($app->team->members->where('invite_status', 'diterima')->sortByDesc('is_leader') as $member)
        @php
            $student = $member->user;
            $grades = $student->course_grades ?? [];
        @endphp
        <li class="rounded-lg border border-rule px-4 py-4">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="font-semibold text-ink">{{ $student->name }}</p>
                    <p class="code text-sm text-muted">{{ $student->nim }}</p>
                </div>
                <span class="shrink-0 rounded-md border px-2 py-0.5 text-caption font-medium {{ $member->is_leader ? 'border-brand bg-brand-tint text-brand-deep' : 'border-rule bg-sunken text-muted' }}">{{ $member->is_leader ? 'Ketua' : 'Anggota' }}</span>
            </div>

            {{-- Garis pemisah identitas (nama/NIM) dari data akademik, selebar kartu --}}
            <dl class="-mx-4 mt-3 grid gap-3 border-t border-rule px-4 pt-3 text-sm sm:grid-cols-3">
                <div>
                    <dt class="text-muted">Asal daerah</dt>
                    <dd class="mt-0.5 text-ink">{{ $student->origin_region ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-muted">Peminatan</dt>
                    <dd class="mt-0.5 text-ink">{{ $student->specialization ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-muted">Jumlah SKS lulus</dt>
                    <dd class="mt-0.5 tabular text-ink">{{ $student->sks_passed ?? '—' }}</dd>
                </div>
                @foreach ($courses as $course)
                    <div>
                        <dt class="text-muted">Nilai {{ $course->name }}</dt>
                        <dd class="mt-0.5 tabular text-ink">{{ $grades[$course->code] ?? '—' }}</dd>
                    </div>
                @endforeach
                <div>
                    <dt class="text-muted">KHS</dt>
                    <dd class="mt-0.5">
                        @if ($member->khs_path)
                            <a href="{{ route('attachments.download', [$app->team, $member, 'khs']) }}" target="_blank" rel="noopener" class="link inline-flex items-center gap-1">
                                <x-heroicon-o-document-text class="h-4 w-4" /> Lihat KHS
                            </a>
                        @else
                            <span class="text-muted">Belum diunggah</span>
                        @endif
                    </dd>
                </div>
            </dl>
        </li>
    @endforeach
</ul>
