<?php

/*
| Rubrik perilaku peer review — disalin dari dokumen "Alur Capstone Project Apps",
| bagian Peer Review Form (6 aspek, skor 1–5).
*/

return [
    'komunikasi' => [
        'definition' => 'Kemampuan dalam mengekspresikan diri, menyampaikan pandangan serta mendapat informasi dari semua anggota kelompok.',
        'levels' => [
            1 => 'Kurang ekspresif dalam mengungkapkan ide, lebih banyak diam, terlihat enggan mendengarkan rekan yang lain. Atau sebaliknya, menolak informasi yang berbeda dengan pendapat dirinya, dan seringkali menghalangi orang lain untuk mengungkapkan pendapat yang berbeda.',
            2 => 'Lebih sering mengulang opini yang disampaikan orang lain, tanpa menambahkan sesuatu yang berarti atau baru. Enggan berbagi informasi.',
            3 => 'Mampu menyampaikan pemikiran dan opini, mendengarkan opini/pendapat orang lain dan menyemangati rekan lain untuk mengungkapkan pendapatnya.',
            4 => 'Banyak berkontribusi terhadap ide-ide baru dalam tim; mampu menangkap berbagai opini dari rekan yang lain; mampu menggali pertanyaan pada opini rekan yang lain; menyemangati orang untuk memiliki sudut pandang yang berbeda.',
            5 => 'Mampu menangkap perbedaan-perbedaan dalam tim dan menggali ide yang baru dari keberagaman tersebut; mampu mewakili tim dalam menyampaikan posisi dan ide tim secara efektif.',
        ],
    ],
    'koordinasi' => [
        'definition' => 'Kemampuan untuk menyusun rencana, mengatur dan membagi tugas ke anggota tim; mampu mengkoordinasikan dan memprioritaskan aktivitas tim dan mengelola detil operasional.',
        'levels' => [
            1 => 'Umumnya terlihat enggan untuk berkoordinasi dengan anggota tim yang lain; tidak jarang membantu anggota tim lain meskipun telah diminta pertolongannya.',
            2 => 'Kadang-kadang enggan untuk membantu menyusun rencana dan berkoordinasi dengan anggota tim lain; kadang menolak untuk membantu rekan yang lain.',
            3 => 'Dapat berkoordinasi dengan anggota lain; berusaha untuk menyelesaikan tugas tepat waktu tanpa diminta; seringkali menolong atau memberi instruksi pada rekan tim yang lain.',
            4 => 'Memberi masukan/perbaikan serta membantu anggota tim lain untuk bekerja lebih efektif; menolong untuk mengontrol deadline; mampu menyusun tugas dan memberikan delegasi tugas pada anggota lain; selalu bersedia untuk membantu anggota tim yang lain.',
            5 => 'Memiliki peran yang penting dalam mengatur aktivitas dan tugas yang perlu dilakukan oleh tim dan membaginya pada anggota kelompok yang lain; menyusun deadline berdasarkan perbedaan tugas; menegur bila ada anggota tim yang tidak mengerjakan tugasnya; menunjukkan kemampuan yang sangat baik dalam mengklarifikasi tugas tim, menunjukkan hal yang perlu dilakukan agar tim berhasil dalam tugas serta memberikan instruksi yang jelas dan sistematis pada anggota tim yang lain.',
        ],
    ],
    'keputusan' => [
        'definition' => 'Kemampuan untuk mengambil keputusan dalam kelompok secara efektif, membantu mengatasi hambatan, menyelesaikan konflik dan negosiasi penggunaan sumber daya.',
        'levels' => [
            1 => 'Bertahan dengan “cara saya atau tidak sama sekali”; bekerja berlawanan dengan keputusan kelompok; selalu berdebat.',
            2 => 'Enggan untuk berpartisipasi dalam keputusan kelompok; sering mempermasalahkan orang-orang yang terlibat dibandingkan fokus pada penyelesaian masalah.',
            3 => 'Berpartisipasi secara efektif dalam keputusan kelompok; bekerja untuk mengimplementasikan keputusan kelompok.',
            4 => 'Mampu mengajak orang lain untuk berkompromi; efektif dalam membantu untuk mengatasi kebuntuan yang dihadapi.',
            5 => 'Mampu mengambil keputusan secara terorganisir sesuai dengan kerangka logis; efektif dalam menerapkan proses pengambilan keputusan kelompok (misal melalui mekanisme voting); terampil dalam menyelesaikan perbedaan pendapat dan konflik.',
        ],
    ],
    'interpersonal' => [
        'definition' => 'Kemampuan dalam bekerja dengan orang lain, menunjukkan rasa empati dan memperlakukan anggota kelompok dengan hormat, serta mendorong solidaritas dan membangun kepercayaan antar anggota kelompok.',
        'levels' => [
            1 => 'Bersikap kasar dan tidak menghormati orang lain; berperilaku kurang baik kepada orang lain dengan latar belakang, jenis kelamin, ras, atau kemampuan yang berbeda.',
            2 => 'Sering meremehkan orang lain; memiliki kecenderungan/tendensi pada suatu kelompok tertentu; tidak mudah percaya orang lain.',
            3 => 'Membina hubungan baik dengan orang lain; berinteraksi secara efektif dengan orang-orang dari latar belakang yang berbeda.',
            4 => 'Berinteraksi secara tepat dengan seluruh anggota kelompok; membantu mempromosikan berlangsungnya keharmonisan dalam kelompok.',
            5 => 'Mampu membangun suasana penerimaan dan kepercayaan dalam kelompok; menunjukkan perilaku sosial yang efektif dalam mendukung keharmonisan pada kelompok; mengenali dan membantu memperbaiki perilaku diskriminatif atau tidak pantas terhadap orang lain.',
        ],
    ],
    'kepemimpinan' => [
        'definition' => 'Kemampuan untuk mengembangkan visi dan arah untuk kelompok; mampu menentukan tujuan dan standar kinerja; mampu menginspirasi dan memberikan energi positif kepada anggota kelompok untuk mencapai kinerja tinggi; memantau pencapaian kinerja kelompok dan individu.',
        'levels' => [
            1 => 'Tidak memberikan pendapat; menunggu perintah/arahan dari orang lain.',
            2 => 'Beberapa kali memberikan pendapat; tidak mau untuk mengambil inisiatif.',
            3 => 'Mampu memberikan pendapat secara bersungguh-sungguh; termasuk ke dalam pemikir yang independen; kadang-kadang mengambil inisiatif.',
            4 => 'Efektif dalam membujuk anggota kelompok untuk mengambil suatu posisi atau memanfaatkan situasi; mengambil banyak inisiatif tindakan; mampu memberikan klarifikasi atas tujuan yang hendak dicapai.',
            5 => 'Mampu menginspirasi orang lain; menetapkan standar dan ekspektasi yang tinggi untuk diri sendiri dan kelompok; berperan penting dalam penentuan arah bagi kelompok dan menjaga kelompok agar tetap fokus dalam pencapaian tujuan.',
        ],
    ],
    'kontribusi' => [
        'definition' => 'Representasi kontribusi untuk penyelesaian tugas dalam rangka pencapaian tujuan.',
        'levels' => [
            1 => 'Tidak ada kontribusi yang adil di dalam tim, sehingga hasil tidak maksimal.',
            2 => 'Hanya sebagian anggota tim yang berkontribusi dalam tim.',
            3 => 'Ada pembagian kerja yang adil di dalam tim.',
            4 => 'Ada pembagian kerja tim sesuai proporsi dengan mempertimbangkan kekuatan dan kelemahan dari setiap anggota tim untuk menghasilkan kerja tim yang efektif dan efisien.',
            5 => 'Setiap individu memiliki kontribusi yang signifikan yang dapat meningkatkan hasil kerja tim.',
        ],
    ],
];
