{{-- Modal "Detail tim" di Monitoring progres, disusun seperti Detail tim registrasi:
     1. "Registrasi diterima …" + Kirim ulang tautan atur password, lalu Ubah tim | Hapus tim (admin/teams/_manage)
     2. Anggota beserta detailnya (admin/teams/_members; tombol Simpan peminatan di samping dropdown)
     3. Topik & Objek Final · Sesi Bimbingan · Deliverable (tanpa tombol Nilai) · Hasil Penilaian dengan tombol Detail ke
        halaman penilaian terkait, lihat saja (shared/_team-progress, viewer admin).
     Memakai state registrationForm dari elemen induk. Butuh $team (members.user, finalTopic, documents, logbook_*,
     assessment_summary, peer_summary, peerReviews), $docMilestones, $minLogbooks, $courses, $specializations, $students, $teamSize. --}}
@php
    $card = 'rounded-lg border border-rule';
    $cardHead = 'border-b border-rule px-4 py-3 text-base font-semibold text-ink sm:px-5';
@endphp
<x-modal width="sm:max-w-4xl" class="text-left font-normal">
    <x-slot:heading>{{ $team->name }}</x-slot:heading>
    <div class="space-y-5 text-left text-body">
        @include('admin.teams._manage')
        @include('admin.teams._members', ['inlineSpec' => true])
        @include('shared._team-progress', ['viewer' => 'admin'])
    </div>
</x-modal>
