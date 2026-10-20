<?php

/*
| Konstanta domain Capstone. Setiap nilai di file ini bersumber dari dokumen
| referensi (FR, PRD, Alur Capstone Project Apps). Nilai yang tidak tertulis di
| dokumen ditandai "PERLU KONFIRMASI" dan dapat diubah tanpa menyentuh kode.
*/

return [

    // F13 — bentuk penilaian. "role" = siapa yang mengisi (F47).
    'components' => [
        'proposal' => ['label' => 'Proposal', 'role' => 'pembimbing'],
        'laporan_progress' => ['label' => 'Laporan Progress', 'role' => 'pembimbing'],
        'sidang_pembimbing' => ['label' => 'Sidang Capstone (Pembimbing)', 'role' => 'pembimbing'],
        'sidang_penguji' => ['label' => 'Sidang Capstone (Penguji)', 'role' => 'penguji'],
        // F47: "sebagai penguji, dosen menilai laporan akhir, presentasi akhir, dan pameran"
        'pameran' => ['label' => 'Pameran', 'role' => 'penguji'],
        'peer_review' => ['label' => 'Peer Review', 'role' => 'mahasiswa'],
    ],

    // F14 — skema penilaian indikator. "oral" = memakai faktor pemahaman (F16).
    'schemes' => [
        'dokumen' => ['label' => 'Dokumen', 'oral' => false],
        'lisan' => ['label' => 'Lisan', 'oral' => true],
        'dokumen_lisan' => ['label' => 'Dokumen + Lisan', 'oral' => true],
        'observasi' => ['label' => 'Observasi', 'oral' => false],
        'peer_observasi' => ['label' => 'Peer + Observasi', 'oral' => false],
    ],

    // F15 — tingkat skor rubrik dokumen
    'score_levels' => [
        5 => ['label' => 'Sangat Baik', 'value' => 95],
        4 => ['label' => 'Baik', 'value' => 80],
        3 => ['label' => 'Cukup', 'value' => 70],
        2 => ['label' => 'Kurang', 'value' => 50],
        1 => ['label' => 'Sangat Kurang', 'value' => 40],
        0 => ['label' => 'Tidak Mengumpulkan', 'value' => 0],
    ],

    // F28 / Alur §3.9 — 6 aspek kerja tim, skor 1–5
    'peer_aspects' => [
        'komunikasi' => 'Kemampuan komunikasi dan memfasilitasi tim',
        'koordinasi' => 'Merencanakan, mengorganisasikan, dan berkoordinasi',
        'keputusan' => 'Pengambilan keputusan dan penyelesaian konflik',
        'interpersonal' => 'Interpersonal dan kolaboratif',
        'kepemimpinan' => 'Kepemimpinan dan pemantauan kinerja',
        'kontribusi' => 'Kontribusi kinerja',
    ],

    // PRD §4.2 documents.doc_type
    'document_types' => [
        'proposal' => 'Proposal',
        'laporan_progress' => 'Laporan Progress',
        'laporan_akhir' => 'Laporan Akhir',
        'materi_presentasi' => 'Materi Presentasi',
        'poster' => 'Poster',
    ],

    // Jenis milestone: "dokumen" masuk ke pengumpulan (submission) mahasiswa; "lainnya" hanya tampil di dashboard & timeline.
    'milestone_categories' => [
        'dokumen' => 'Dokumen',
        'lainnya' => 'Lainnya',
    ],

    'milestone_kinds' => [
        'registrasi_tim' => 'Registrasi Tim',
        'pengajuan_topik' => 'Pengajuan Topik Mandiri',
        'apply_topik_dosen' => 'Apply Topik Dosen',
        'dokumen' => 'Pengumpulan Dokumen',
        'bimbingan' => 'Bimbingan',
        'sidang' => 'Sidang Capstone',
        'pameran' => 'Pameran',
        'peer_review' => 'Peer Review',
        'lainnya' => 'Lainnya',
    ],

    // F21 — ukuran tim
    'team_size' => ['min' => 2, 'max' => 3],

    // F27 — minimal sesi bimbingan disetujui sebagai syarat presentasi akhir
    'min_approved_logbooks' => 8,

    // F19 — tenggat dalam N hari ditandai "segera" di dashboard mahasiswa
    'dashboard_soon_days' => 7,

    // F19 — tahap pada timeline dashboard mahasiswa. Setiap tahap merangkum milestone menurut
    // kind atau "dokumen:<document_type>". Milestone yang tidak disebut (mis. bimbingan) tidak tampil.
    'dashboard_stages' => [
        'Registrasi Tim' => ['registrasi_tim'],
        'Penentuan Topik' => ['pengajuan_topik', 'apply_topik_dosen'],
        'Proposal' => ['dokumen:proposal'],
        'Laporan Progress' => ['dokumen:laporan_progress'],
        'Laporan Akhir' => ['dokumen:laporan_akhir', 'dokumen:materi_presentasi', 'sidang'],
        'Pameran' => ['dokumen:poster', 'pameran', 'peer_review'],
    ],

    // Pilihan tipe file yang dapat diizinkan admin per milestone dokumen (ekstensi => label)
    'document_file_types' => [
        'pdf' => 'PDF', 'docx' => 'DOCX', 'pptx' => 'PPTX', 'xlsx' => 'XLSX', 'jpg' => 'JPG', 'png' => 'PNG', 'zip' => 'ZIP',
    ],

    // NFR — tipe file unggahan (document_mimes = bawaan milestone yang belum diatur admin)
    'upload' => [
        'document_mimes' => ['pdf', 'docx', 'pptx', 'jpg'],
        'document_max_kb' => 10240,
        'attachment_mimes' => ['pdf', 'jpg', 'jpeg', 'png'],
        'attachment_max_kb' => 5120,
    ],

    'prodi_categories' => [
        'dosen_kelas' => 'Dosen kelas',
        'non_pengampu' => 'Non pengampu kelas',
    ],

    // F49 / F56 — jenis pekerjaan dosen yang dipantau
    'reminder_job_types' => [
        'penilaian' => 'Pengisian nilai',
        'approval_logbook' => 'Approval log bimbingan',
        'approval_pelamar' => 'Approval tim pelamar topik',
    ],

    // PERLU KONFIRMASI — dokumen tidak mencantumkan skala grade.
    // Batas bawah inklusif, diurutkan dari tertinggi.
    'grade_scale' => [
        ['grade' => 'A', 'min' => 80],
        ['grade' => 'AB', 'min' => 70],
        ['grade' => 'B', 'min' => 65],
        ['grade' => 'BC', 'min' => 60],
        ['grade' => 'C', 'min' => 50],
        ['grade' => 'D', 'min' => 40],
        ['grade' => 'E', 'min' => 0],
    ],

    'automation' => [
        // Token yang wajib dikirim n8n pada header X-Automation-Token (F56)
        'token' => env('AUTOMATION_TOKEN'),
        'webhook_url' => env('N8N_REMINDER_WEBHOOK_URL'),
        'max_attempts' => (int) env('REMINDER_MAX_ATTEMPTS', 3),
    ],

    'drive' => [
        'enabled' => (bool) env('GOOGLE_DRIVE_ENABLED', false),
        'credentials_path' => env('GOOGLE_DRIVE_CREDENTIALS'),
        'share_with_owner' => (bool) env('GOOGLE_DRIVE_SHARE_WITH_OWNER', true),
        'max_attempts' => (int) env('GOOGLE_DRIVE_MAX_ATTEMPTS', 3),
    ],

    // DATA DEMO — tampilkan daftar akun dummy di halaman login. Matikan (DEV_LOGIN=false) saat implementasi riil.
    'dev_login' => (bool) env('DEV_LOGIN', false),

    // DATA DEMO — akun dummy dari DemoSeeder, semuanya ber-password demo_password. Hapus saat implementasi riil.
    'demo_password' => 'password',
    'demo_accounts' => [
        'Admin' => ['admin@gmail.com'],
        'Mahasiswa' => ['mahasiswa@gmail.com'],
        'Dosen' => ['dosen@gmail.com'],
    ],
];
