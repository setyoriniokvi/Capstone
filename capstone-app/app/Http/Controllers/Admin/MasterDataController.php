<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssessmentComponent;
use App\Models\MasterDataLog;
use App\Models\Period;
use App\Models\Team;
use App\Services\MasterData\ProcessLogs;
use App\Services\ScoreCalculator;
use App\Services\TeamRegistration;
use App\Support\PerPage;
use App\Support\ProcessTable;
use App\Support\Sorting;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Master Data (menu paling bawah sidebar admin), tab Master Data Mahasiswa / Master Data Dosen. Setiap proses di sistem
 * punya kartu sendiri dengan Cari | Sort | filter dan "Tampilkan [n] data" + ‹ › (parameter berawalan kunci kartu,
 * lihat App\Support\ProcessTable):
 *   Mahasiswa: Log Registrasi Tim · Pengajuan Topik · Log Bimbingan · Log Submission · Log Peer Review
 *   Dosen    : Status Dosen (pembimbing/penguji) · Pengajuan Topik Dosen · Log Penilaian
 * Log perubahan data (MasterDataLogger) tetap tercatat di database, tetapi kartunya tidak ditampilkan.
 */
class MasterDataController extends Controller
{
    public function index(Request $request, TeamRegistration $registration, ScoreCalculator $calculator): View
    {
        $tab = $request->input('tab') === 'dosen' ? 'dosen' : 'mahasiswa';

        $period = Period::workspace();
        $data = [];
        if ($period) {
            $data = $tab === 'mahasiswa'
                ? $this->studentCards($request, $period, $registration)
                : $this->lecturerCards($request, $period, $calculator);
        }

        return view('admin.master-data.index', $data + ['tab' => $tab]);
    }

    private function studentCards(Request $request, Period $period, TeamRegistration $registration): array
    {
        $logs = new ProcessLogs($period);

        // Log Registrasi Tim (keputusan diterima / ditolak). Sort memakai parameter lama (urut_riwayat / arah_riwayat).
        $decided = Team::query()->where('period_id', $period->id)->whereIn('status', ['diterima', 'ditolak'])
            ->when($request->filled('riwayat_q'), function ($q) use ($request) {
                $term = '%'.$request->string('riwayat_q').'%';
                $q->where(fn ($q) => $q->where('name', 'like', $term)
                    ->orWhereHas('users', fn ($u) => $u->where('name', 'like', $term)->orWhere('nim', 'like', $term)->orWhere('email', 'like', $term)));
            })
            ->when(in_array($request->input('riwayat_status'), ['diterima', 'ditolak'], true), fn ($q) => $q->where('status', $request->input('riwayat_status')))
            ->with(['members' => fn ($m) => $m->orderByDesc('is_leader'), 'members.user']);
        Sorting::apply($decided, $request, ['tim' => 'name', 'terbaru' => 'reviewed_at', 'kategori' => 'status'], 'tim', 'asc', 'urut_riwayat', 'arah_riwayat');
        $perRiwayat = PerPage::from($request, 'riwayat_per', ProcessTable::DEFAULT_PER_PAGE, ProcessTable::PER_PAGE);

        return [
            'decided' => $decided->paginate($perRiwayat, ['*'], 'riwayat')->withQueryString()->fragment('riwayat'),
            'openTeam' => $request->integer('tim'),
            'students' => $registration->availableStudents($period),
            'topicLog' => ProcessTable::paginate($request, 'topik', $logs->studentTopics(),
                ['tanggal' => 'Tanggal', 'tim' => 'Tim', 'judul' => 'Judul topik', 'kategori' => 'Kategori (Status)'], 'tanggal', 'desc', ['jenis', 'status']),
            'lecturerTopicApplicationLog' => ProcessTable::paginate($request, 'lamaran_topik', $logs->lecturerTopicApplications(),
                ['tanggal' => 'Diputuskan', 'tim' => 'Tim', 'judul' => 'Judul topik', 'kategori' => 'Kategori (Keputusan)'], 'tanggal', 'desc', ['status']),
            'logbookLog' => ProcessTable::paginate($request, 'bimbingan', $logs->logbooks(),
                ['tanggal' => 'Tanggal sesi', 'tim' => 'Tim', 'sesi' => 'Sesi ke', 'kategori' => 'Kategori (Status)'], 'tanggal', 'desc', ['status']),
            'submissionLog' => ProcessTable::paginate($request, 'submission', $logs->submissions(),
                ['tanggal' => 'Waktu unggah', 'tim' => 'Tim', 'kategori' => 'Kategori (Dokumen)'], 'tanggal', 'desc', ['dokumen', 'review']),
            'peerLog' => ProcessTable::paginate($request, 'peer', $peer = $logs->peerReviews(),
                ['tanggal' => 'Waktu', 'tim' => 'Tim', 'nama' => 'Nama penilai', 'skor' => 'Rata-rata skor'], 'tanggal', 'desc', ['tim', 'status']),
            'peerTeams' => $peer->pluck('team')->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values(),
        ] + $registration->options($period);
    }

    private function lecturerCards(Request $request, Period $period, ScoreCalculator $calculator): array
    {
        $logs = new ProcessLogs($period);

        return [
            'assignmentLog' => ProcessTable::paginate($request, 'status_dosen', $logs->lecturerAssignments(),
                ['tanggal' => 'Tanggal', 'nama' => 'Nama dosen', 'tim' => 'Tim', 'kategori' => 'Kategori (Peran)'], 'tanggal', 'desc', ['peran', 'sumber']),
            'lecturerTopicLog' => ProcessTable::paginate($request, 'topik_dosen', $logs->lecturerTopics(),
                ['tanggal' => 'Tanggal', 'nama' => 'Nama dosen', 'judul' => 'Judul topik', 'kategori' => 'Kategori (Status)'], 'tanggal', 'desc', ['status']),
            'assessmentLog' => ProcessTable::paginate($request, 'penilaian', $logs->assessments($calculator),
                ['tanggal' => 'Waktu', 'nama' => 'Nama dosen', 'tim' => 'Tim', 'nilai' => 'Nilai', 'kategori' => 'Kategori (Bentuk penilaian)'], 'tanggal', 'desc', ['bentuk', 'status']),
            'components' => collect(AssessmentComponent::lecturerConfig())->map(fn ($c) => $c['label']),
        ];
    }
}
