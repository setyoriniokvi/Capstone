# Capstone — Fakultas Rekayasa Industri, Telkom University

Aplikasi web untuk mengelola siklus mata kuliah Capstone Design & Project selama 16 minggu: registrasi tim, topik, plotting dosen, bimbingan, dokumen milestone, penilaian berbasis rubrik, peer review, sampai rekap nilai akhir. Ada tiga peran: **Admin**, **Mahasiswa**, dan **Dosen**.

```
Capstone/
├── capstone-app/                 Aplikasi Laravel 11 (PHP 8.2+, Blade, Tailwind, Alpine)
├── n8n/
│   └── capstone-reminder-workflow.json   Workflow n8n siap impor (pengingat email & WhatsApp)
├── .claude/skills/hallmark/      Skill desain anti "AI slop" (Nutlope/hallmark)
└── (dokumen referensi: FR, PRD, BPMN, flowchart, logo, rubrik)
```

## Menjalankan secara lokal

```bash
cd capstone-app
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
```

Untuk mencoba cepat tanpa MySQL, ubah `.env`:

```dotenv
DB_CONNECTION=sqlite        # lalu: touch database/database.sqlite
APP_ENV=local
DEV_LOGIN=true              # menampilkan daftar akun demo di bawah form login
```

```bash
php artisan migrate --seed                      # master data: prodi, 8 CLO, rubrik lisan
php artisan db:seed --class=DemoSeeder          # opsional: data contoh untuk mencoba alur
php artisan serve                               # http://127.0.0.1:8000
php artisan queue:work                          # wajib bila sinkronisasi Google Drive aktif
php artisan schedule:work                       # notifikasi tenggat mahasiswa (tiap jam)
php artisan test                                # 36 test fitur & alur
```

## Setelah `git pull`

Hasil build tampilan (`public/build`) tidak disimpan di git, jadi setiap selesai pull jalankan dari folder `capstone-app`:

```bash
composer install        # bila ada package PHP baru
npm install             # bila ada package JS baru
php artisan migrate     # bila ada migration baru
npm run build           # wajib: bangun ulang CSS, JS, dan gambar
```

Selama mengembangkan tampilan, `npm run dev` lebih praktis karena perubahan langsung terlihat tanpa build ulang. Jangan mengedit file di `public/build` — isinya ditimpa setiap build; yang diedit selalu `resources/`.

### Letak gambar

| Folder | Isi | Cara memakai |
| --- | --- | --- |
| `resources/images/` | Semua gambar aplikasi (logo, foto login) | Blade: `{{ Vite::asset('resources/images/nama.png') }}` · CSS: `url('../images/nama.jpg')` |
| `public/icons/` | Ikon browser (favicon, apple-touch-icon) — URL-nya harus tetap | `{{ asset('icons/favicon.png') }}` |

Gambar di `resources/images` diberi nama ber-hash saat build, sehingga browser otomatis memuat versi terbaru bila gambar diganti.

Akun demo (password semua akun `password`, daftarnya tampil di bawah form login bila `DEV_LOGIN=true`): `admin@gmail.com`, `mahasiswa@gmail.com`, `dosen@gmail.com`. Data demo lain memakai `mahasiswa2`–`mahasiswa14@gmail.com` dan `dosen2`–`dosen5@gmail.com`. Semua nama dan email pada `DemoSeeder` adalah contoh, bukan data resmi.

## Konfigurasi produksi

| Kebutuhan | Variabel `.env` | Catatan |
| --- | --- | --- |
| Database (PRD §3.1) | `DB_CONNECTION=mysql`, `DB_*` | MySQL/MariaDB 8 |
| Login (F1) | `DEV_LOGIN=false` | Login email + password. Password awal akun impor: mahasiswa = NIM, dosen = kode dosen; admin dapat menggantinya di form edit. `DEV_LOGIN=false` menyembunyikan daftar akun demo |
| Google Drive (F33–F41) | `GOOGLE_DRIVE_ENABLED=true`, `GOOGLE_DRIVE_CREDENTIALS` | Service account JSON. Folder tujuan sebaiknya di **Shared Drive** yang dibagikan ke email service account. ID folder per prodi diatur di menu Periode |
| n8n (F49–F57) | `AUTOMATION_TOKEN`, `N8N_REMINDER_WEBHOOK_URL` | Token acak panjang; URL webhook diambil dari node *Pemicu manual* |

Hanya akun yang sudah terdaftar (diimpor / ditambah admin) yang bisa login. Peran dibaca dari data akun.

## Workflow n8n

1. Di n8n: **Workflows → Import from File** → pilih `n8n/capstone-reminder-workflow.json`.
2. Ikuti catatan di kanvas: ubah `base_url`, buat kredensial `Capstone Automation Token` (Header Auth, `X-Automation-Token`), `Fonnte Token` (Header Auth, `Authorization`), dan `SMTP Capstone`.
3. Aktifkan workflow. Ia berjalan tiap jam dan juga dipicu langsung saat admin menekan "Kirim reminder".

Alurnya: n8n memanggil `POST /api/automation/reminders/claim` → backend mengembalikan pesan yang jatuh tempo (sudah dicek ulang apakah pekerjaannya masih belum selesai, dan sudah dicegah duplikatnya) → n8n mengirim email atau WhatsApp → n8n melaporkan hasil ke `POST /api/automation/reminders/{id}/report`. Pengiriman yang gagal dicoba ulang otomatis hingga `REMINDER_MAX_ATTEMPTS`, dan admin bisa mengirim ulang dari menu **Notifikasi Otomatis**.

## Peta Functional Requirement

| FR | Lokasi di aplikasi |
| --- | --- |
| F1 | Login email + password, pengalihan ke dashboard sesuai peran |
| F2 | Admin → Periode & Timeline (milestone, tenggat, jendela penilaian, folder Drive per prodi) |
| F3, F4 | Admin → Dosen / Mahasiswa / Registrasi Tim, termasuk impor Excel/CSV dengan pratinjau per baris |
| F5, F6 | Admin → Aturan Registrasi; screening otomatis (flag + skor) di detail tim |
| F7, F8 | Admin → Approval Topik (cek kata kunci terlarang), Rekap Topik Final |
| F9, F10 | Admin → Kuota Dosen (global / kategori / per dosen), Plotting Dosen dengan flag OVER/UNDER dan tersedia/penuh |
| F11, F12 | Admin → Progres Tim, Penilaian & Reminder |
| F13–F16 | Admin → Master Penilaian (CLO, indikator, rubrik dokumen, rubrik lisan) |
| F17, F20, F45 | File Pendukung |
| F18 | Rekap Nilai (admin & dosen), ekspor XLSX satu baris per mahasiswa, penuh atau parsial |
| F19, F21–F30 | Menu mahasiswa: dashboard, tim, topik, bank topik, dokumen, log bimbingan, peer review, hasil, QR |
| F31, F32, F58–F61 | `/q/{token}`: validasi QR di backend, wajib login, hanya untuk dosen tim dan tahap aktif |
| F33–F41 | Unggah dokumen → sinkronisasi Drive di antrean, revisi memperbarui file yang sama, Trash/permanen, log & retry |
| F42–F47 | Menu dosen: topik, tim pelamar, detail tim, approval bimbingan, penilaian berbasis rubrik |
| F48 | Notifikasi dalam aplikasi + `capstone:notify-deadlines` |
| F49–F57 | `ReminderService` + API automation + workflow n8n |

## Keputusan yang diambil saat dokumen berbeda

Functional Requirement dijadikan acuan utama, sesuai arahan.

- **Login**: email + password dari akun yang diimpor / ditambah admin. Daftar akun demo di halaman login hanya tampil bila `DEV_LOGIN=true`.
- **Admin**: satu peran Admin dengan filter prodi (sesuai pilihan Anda).
- **Pameran dinilai penguji**: mengikuti F47. PRD dan BPMN menyebut pembimbing atau pihak eksternal.
- **Bentuk penilaian** mengikuti F13 (Proposal, Laporan Progress, Sidang Pembimbing, Sidang Penguji, Peer Review, Pameran). Bobot "Laporan Akhir + Presentasi Akhir" dari tabel PRD digabung ke Sidang.
- **Skala skor** mengikuti F15 (5 = 95 … 0 = 0), bukan skala 50–95 di PRD.
- **Screening** memberi flag dan skor, lalu admin yang memutuskan (F6). Sistem hanya memblokir hal yang jelas: jumlah anggota, undangan yang belum dijawab, dan lampiran yang belum lengkap.
- **Akses dokumen**: mahasiswa hanya bisa membuka dokumen yang ia unggah sendiri (F36). Rekan satu tim hanya melihat bahwa dokumen sudah dikumpulkan.
- **Gate**: unggah dokumen dibuka setelah topik final ada. Materi presentasi dan penilaian sidang menunggu minimal 8 log bimbingan disetujui. Nilai akhir terbuka setelah peer review diisi.
- **Rubrik Level AI** belum dipakai (sesuai pilihan Anda).

## Perlu konfirmasi

1. **Skala grade** (A, AB, B, …) tidak tercantum di dokumen. Nilai sementara ada di `config/capstone.php → grade_scale`.
2. **Template iGadis** tidak dilampirkan. Ekspor memakai kolom dari dokumen Alur §6.6 (NIM, Nama, Kode Kelompok, Prodi, CLO1–CLO8, Total, Grade).
3. **Daftar indikator penilaian** (F14) dan deskripsi rubriknya belum ada di dokumen. `DemoSeeder` membuat satu indikator contoh per CLO; isi yang resmi lewat menu Master Penilaian.
4. **Faktor pemahaman** rubrik lisan: FR hanya menyebut rentang 0,25–1,00. Nilai level 2–4 dibagi rata dan kolom perilaku masih kosong.
5. **Mata kuliah prasyarat** per prodi belum disebutkan. Demo memakai "KP — Kerja Praktek (contoh)".
