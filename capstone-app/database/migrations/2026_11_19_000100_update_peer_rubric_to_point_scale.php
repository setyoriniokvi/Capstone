<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Rubrik peer review baru: 6 aspek (nama & deskripsi level dari dokumen rubrik terbaru) di semua periode dan template,
 * aspek lain dihapus beserta nilainya. Skala nilai berubah dari 1–5 menjadi 0 / 50 / 65 / 75 / 90: skor peer review
 * yang sudah ada dikonversi per level (1→0, 2→50, 3→65, 4→75, 5→90).
 */
return new class extends Migration
{
    private const POINTS = [1 => 0, 2 => 50, 3 => 65, 4 => 75, 5 => 90];

    public function up(): void
    {
        $aspects = [
            'komunikasi' => ['name' => 'Kemampuan Komunikasi dan Memfasilitasi Tim', 'levels' => [
                'Kurang ekspresif dalam mengungkapkan ide, lebih banyak diam, terlihat enggan mendengarkan rekan yang lain. Atau sebaliknya, menolak informasi yang berbeda dengan pendapat dirinya, dan seringkali menghalangi orang lain untuk mengungkapkan pendapat yang berbeda.',
                'Lebih sering mengulang opini yang disampaikan orang lain, tanpa menambahkan sesuatu yang berarti atau baru. Enggan berbagi informasi.',
                'Mampu menyampaikan pemikiran dan opini, mendengarkan opini/ pendapat orang lain dan menyemangati rekan lain untuk mengungkapkan pendapatnya.',
                'Banyak berkontribusi terhadap ide-ide baru dalam tim, mampu menangkap berbagai opini dari rekan yang lain, mampu menggali pertanyaan pada opini rekan yang lain, menyemangati orang untuk memiliki sudut pandang yang berbeda',
                'Mampu menangkap perbedaan-perbedaan dalam tim dan menggali ide yang baru dari keberagaman tersebut, mampu mewakili tim dalam menyampaikan posisi dan ide tim secara efektif.',
            ]],
            'koordinasi' => ['name' => 'Kemampuan Merencanakan, Mengorganisasikan dan Melakukan Koordinasi', 'levels' => [
                'Umumnya terlihat enggan untuk berkoordinasi dengan anggota tim yang lain; jarang membantu anggota tim lain meskipun telah diminta pertolongannya.',
                'Kadang-kadang enggan untuk membantu menyusun rencana dan berkoordinasi dengan anggota tim lain; kadang menolak untuk membantu rekan yang lain',
                'Dapat berkoordinasi dengan anggota lain; berusaha untuk menyelesaikan tugas tepat waktu tanpa diminta; seringkali menolong atau memberi instruksi pada rekan tim yang lain.',
                'Memberi masukan/ perbaikan serta membantu anggota tim lain untuk bekerja lebih efektif; menolong untuk mengontrol deadline; mampu Menyusun tugas dan memberikan delegasi tugas pada anggota lain; selalu bersedia untuk membantu anggota tim yang lain.',
                'Memiliki peran yang penting dalam mengatur aktivitas dan tugas yang perlu dilakukan oleh tim dan membaginya pada anggota kelompok yang lain; Menyusun deadline berdasarkan perbedaan tugas; menegur bila ada anggota tim yang tidak mengerjakan tugasnya; menunjukkan kemampuan yang sangat baik dalam mengklarifikasi tugas tim, menunjukkan hal yang perlu dilakukan agar tim berhasil dalam tugas serta memberikan instruksi yang jelas dan sistematis pada anggota tim yang lain.',
            ]],
            'keputusan' => ['name' => 'Kemampuan Pengambilan Keputusan dan Penyelesaian Konflik', 'levels' => [
                'Bertahan dengan “cara saya atau tidak sama sekali”; bekerja berlawanan dengan keputusan kelompok; selalu berdebat.',
                'Enggan untuk berpartisipasi dalam keputusan kelompok; sering mempermasalahkan orang-orang yang terlibat dibandingkan fokus pada penyelesaian masalah.',
                'Berpartisipasi secara efektif dalam keputusan kelompok; bekerja untuk mengimplementasikan keputusan kelompok.',
                'Mampu mengajak orang lain untuk berkompromi; efektif dalam membantu untuk mengatasi kebuntuan yang dihadapi.',
                'Mampu mengambil keputusan secara terorganisir sesuai dengan kerangka logis; efektif dalam menerapkan proses pengambilan keputusan kelompok (misal melalui mekanisme voting); terampil dalam menyelesaikan perbedaan pendapat dan konflik.',
            ]],
            'interpersonal' => ['name' => 'Kemampuan Interpersonal dan Kolaboratif', 'levels' => [
                'Bersikap kasar dan tidak menghormati orang lain; berperilaku kurang baik kepada orang lain dengan latar belakang, jenis kelamin, ras, atau kemampuan yang berbeda.',
                'Sering meremehkan rendah orang lain; memiliki kecenderungan/tendensi pada suatu kelompok tertentu; tidak mudah percaya orang lain.',
                'Membina hubungan baik dengan orang lain; berinteraksi secara efektif dengan orang-orang dari latar belakang yang berbeda.',
                'Berinteraksi secara tepat dengan seluruh anggota kelompok; membantu mempromosikan berlangsungnya keharmonisan dalam kelompok.',
                'Mampu membangun suasana penerimaan dan kepercayaan dalam kelompok; menunjukkan perilaku sosial yang efektif dalam mendukung keharmonisan pada kelompok; mengenali dan membantu memperbaiki perilaku diskriminatif atau tidak pantas terhadap orang lain.',
            ]],
            'kepemimpinan' => ['name' => 'Kemampuan Kepemimpinan dan Pemantauan Kinerja', 'levels' => [
                'Tidak memberikan pendapat; menunggu perintah/arahan dari orang lain.',
                'Beberapa kali memberikan pendapat; tidak mau untuk mengambil inisiatif.',
                'Mampu memberikan pendapat secara bersungguh-sungguh; termasuk ke dalam pemikir yang independen; kadang-kadang mengambil insiatif.',
                'Efektif dalam membujuk anggota kelompok untuk mengambil suatu posisi atau memanfaatkan situasi; mengambil banyak inisiatif tindakan; mampu memberikan klarifikasi atas tujuan yang hendak dicapai.',
                'Mampu menginspirasi orang lain; menetapkan standar dan ekspektasi yang tinggi untuk diri sendiri dan kelompok; berperan penting dalam penentuan arah bagi kelompok dan menjaga kelompok agar tetap fokus dalam pencapaian tujuan.',
            ]],
            'kontribusi' => ['name' => 'Kontribusi Kinerja', 'levels' => [
                'Tidak ada kontribusi yang adil di dalam tim, sehingga hasil tidak masimal',
                'Hanya sebagian anggota tim yang berkontribusi dalam tim',
                'Ada pembagian kerja yang adil di dalam tim',
                'Ada pembagian kerja tim sesuai proporsi dengan mempertimbangkan kekuatan dan kelemahan dari setiap anggota tim untuk menghasilkan kerja tim yang efektif dan efisien',
                'Setiap individu memiliki kontribusi yang signifikan yang dapat meningkatkan hasil kerja tim',
            ]],
        ];
        $definitions = collect(require config_path('peer_rubric.php'))->map(fn ($a) => $a['definition']);
        $periods = DB::table('peer_aspects')->distinct()->pluck('period_id');
        if ($periods->isEmpty()) {
            $periods = collect([null]);
        }
        foreach ($periods as $period) {
            $scope = fn () => DB::table('peer_aspects')->where(fn ($q) => $period === null ? $q->whereNull('period_id') : $q->where('period_id', $period));
            $scope()->whereNotIn('key', array_keys($aspects))->delete();
            $order = 0;
            foreach ($aspects as $key => $aspect) {
                $values = ['name' => $aspect['name'], 'sort_order' => ++$order, 'updated_at' => now()];
                foreach ($aspect['levels'] as $i => $text) {
                    $values['level_'.($i + 1)] = $text;
                }
                if ($scope()->where('key', $key)->exists()) {
                    $scope()->where('key', $key)->update($values);
                } else {
                    DB::table('peer_aspects')->insert($values + ['key' => $key, 'period_id' => $period, 'definition' => $definitions[$key] ?? $aspect['name'], 'created_at' => now()]);
                }
            }
        }
        DB::table('peer_reviews')->whereNotIn('aspect', array_keys($aspects))->delete();
        // Konversi skor lama (hanya yang masih berskala 1–5)
        foreach (self::POINTS as $level => $points) {
            DB::table('peer_reviews')->where('score', $level)->update(['score' => -$points - 1]);
        }
        DB::table('peer_reviews')->where('score', '<', 0)->update(['score' => DB::raw('-score - 1')]);
    }

    public function down(): void
    {
        foreach (self::POINTS as $level => $points) {
            DB::table('peer_reviews')->where('score', $points)->update(['score' => -$level]);
        }
        DB::table('peer_reviews')->where('score', '<', 0)->update(['score' => DB::raw('-score')]);
    }
};
