# Naskah Voice-over — Bumper Capstone (30 detik)

Bahasa Indonesia santai (bukan gaya pembaca berita), seperti orang yang sedang cerita ke teman. Total sekitar 20 detik bicara; sisanya jeda & musik.

**Cara baca kolom:** *Mulai* = kapan kalimat mulai diucapkan di video; *Maks.* = durasi paling lama agar tidak menabrak adegan berikutnya. Kalimat sengaja pendek supaya masih ada napas.

| # | Mulai | Maks. | Adegan | Naskah | Arahan ekspresi |
|---|---|---|---|---|---|
| 1 | 00:00.27 | 2.4 dtk | Intro · logo & judul | **Enam belas minggu... satu alur yang rapi.** | Tenang dan sedikit penasaran, seperti membuka cerita. Jeda ringan di "...", lalu "satu alur" sedikit ditekan. |
| 2 | 00:03.07 | 3.9 dtk | Admin menerima tim | **Syarat dicek otomatis, keputusan akhir... tetap di tangan admin.** | Mantap, yakin. Jeda singkat sebelum "tetap di tangan admin", nada turun, meyakinkan. |
| 3 | 00:07.73 | 2.7 dtk | Dashboard mahasiswa + ponsel | **Mahasiswa bisa lihat semuanya, dalam satu layar.** | Ringan, bersahabat, agak tersenyum. "satu layar" naik sedikit. |
| 4 | 00:11.07 | 3.5 dtk | Dosen menyetujui bimbingan | **Bimbingan disetujui dosen, progres tim langsung naik. Bahkan di HP.** | Dua napas: kalimat pertama santai, "Bahkan di HP." pendek dan kagum. |
| 5 | 00:14.93 | 3.3 dtk | Rubrik jadi nilai | **Nilai? Dihitung otomatis dari rubrik. Dosen tinggal mengisi, selesai.** | "Nilai?" seperti bertanya ke penonton, dijawab santai. "selesai" pendek, mantap. |
| 6 | 00:18.87 | 3.5 dtk | Email pengingat ke dosen | **Ada dosen yang telat menilai? Tenang... pengingatnya terkirim otomatis lewat email.** | Sedikit usil di "telat menilai?", lalu lega di "Tenang...". |
| 7 | 00:22.87 | 2.2 dtk | Rekap nilai + ekspor iGadis | **Nilai akhir terekap, siap diekspor ke iGadis.** | Cepat dan puas, seperti pekerjaan beres. "iGadis" jelas. |
| 8 | 00:25.40 | 2.3 dtk | Penutup & lockup | **Capstone. Enam belas minggu, satu alur.** | Hangat dan percaya diri. "Capstone." berdiri sendiri, jeda, sisanya mengalun turun. |
| 9 | 00:27.87 | 2.1 dtk | Kredit tim | **Dibuat oleh Du Bist Gut Genug.** | Bangga tapi rendah hati, tersenyum di suara. Nama tim diucapkan perlahan dan jelas. |

## Tips rekaman

- Rekam per kalimat (9 file), jangan satu take panjang; mudah diulang dan pas dengan frame.
- Titik-titik (`...`) = jeda kecil sambil menahan napas; tanda tanya benar-benar dinaikkan.
- Bicara sedikit tersenyum; senyum terdengar di suara. Jarak mulut ±15 cm dari mik, ruangan senyap.
- Kalau kalimat lebih panjang dari kolom *Maks.*, percepat sedikit atau pangkas kata — jangan potong napas.

## Memasukkan rekaman ke video

1. Simpan tiap rekaman sebagai WAV dengan nama sesuai id: `video/public/audio/vo/01-hook.wav`, `02-admin.wav`, `03-mhs.wav`, `04-bimbingan.wav`, `05-rubrik.wav`, `06-email.wav`, `07-rekap.wav`, `08-outro.wav`, `09-kredit.wav`.
2. Jalankan `npm run audio` lalu `npm run render` di folder `video/`. Skrip otomatis menaruh tiap file di waktu mulainya dan merendahkan musik/efek saat Anda bicara (ducking).
3. Mau geser waktu mulai atau ganti teks? Ubah `video/src/vo.json` (kolom `f` = nomor frame, 30 frame = 1 detik).

Format file lain (mp3/m4a) belum dibaca skrip — konversi dulu ke WAV (`ffmpeg -i rekaman.m4a 01-hook.wav`).
