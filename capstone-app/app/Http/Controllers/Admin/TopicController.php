<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ForbiddenKeyword;
use App\Models\Period;
use App\Models\RegistrationRule;
use App\Models\StudyProgram;
use App\Models\Topic;
use App\Models\User;
use App\Services\TopicService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * F7 / F8 — approval topik (seperti Approval Registrasi Tim): kartu kata kunci terlarang, lalu tab Topik Mandiri
 * (menunggu approval + riwayat) dan Topik Dosen (menunggu approval + bank topik dosen yang dapat dikelola admin).
 */
class TopicController extends Controller
{
    public function index(Request $request): View
    {
        $period = Period::workspace();
        $tab = ($request->input('tab') ?? $request->input('sumber')) === 'dosen' ? 'dosen' : 'mandiri';
        $base = fn (string $source) => Topic::query()->where('source', $source)
            ->when($period, fn ($q) => $q->where('period_id', $period->id));

        $data = [
            'tab' => $tab,
            'period' => $period,
            'keywords' => ForbiddenKeyword::query()->where('source', $tab)->orderBy('keyword')->get(),
            'pendingCount' => $period ? Topic::query()->where('period_id', $period->id)->where('status', 'menunggu')->selectRaw('source, count(*) as total')->groupBy('source')->pluck('total', 'source') : collect(),
            'pending' => $base($tab)->where('status', 'menunggu')->with(['team', 'lecturer.lecturerProfile', 'proposers'])->oldest('updated_at')->get(),
        ];

        if ($tab === 'mandiri') {
            // Riwayat keputusan: 3 terbaru per halaman (?riwayat=n)
            $data['decided'] = $base('mandiri')->whereIn('status', ['diterima', 'ditolak'])->with('team')
                ->latest('reviewed_at')->latest('id')->paginate(3, ['*'], 'riwayat')->withQueryString();
        } else {
            $data['bank'] = $base('dosen')->where('status', 'diterima')->with(['lecturer', 'proposers'])
                ->withCount([
                    'applications as accepted_count' => fn ($q) => $q->where('status', 'diterima'),
                    'applications as pending_count' => fn ($q) => $q->where('status', 'menunggu'),
                ])->get()->sortBy(fn (Topic $t) => [$t->lecturer?->name, $t->title])->values();
            $data += $this->formData($period);
        }

        return view('admin.topics.index', $data);
    }

    /** Setujui sekaligus topik yang dicentang di tabel "Menunggu Approval"; topik yang sudah diputuskan dilewati. */
    public function approveMany(Request $request, TopicService $topics): RedirectResponse
    {
        $data = $request->validate([
            'topics' => ['required', 'array', 'min:1'],
            'topics.*' => ['integer', 'distinct'],
        ], ['topics.required' => 'Pilih minimal satu topik.']);

        $selected = Topic::query()->whereIn('id', $data['topics'])->where('status', 'menunggu')->get();
        if ($selected->isEmpty()) {
            return back()->withErrors(['topic' => 'Tidak ada topik terpilih yang menunggu keputusan.']);
        }
        $selected->each(fn (Topic $topic) => $topics->review($topic, true, null, $request->user()));

        return back()->with('status', $selected->count().' topik disetujui.');
    }

    /** Tolak sekaligus topik yang dicentang; satu alasan (wajib) dikirim ke setiap pengusul. */
    public function rejectMany(Request $request, TopicService $topics): RedirectResponse
    {
        $data = $request->validate([
            'topics' => ['required', 'array', 'min:1'],
            'topics.*' => ['integer', 'distinct'],
            'notes' => ['required', 'string', 'max:2000'],
        ], ['topics.required' => 'Pilih minimal satu topik.', 'notes.required' => 'Alasan penolakan wajib diisi.']);

        $selected = Topic::query()->whereIn('id', $data['topics'])->where('status', 'menunggu')->get();
        if ($selected->isEmpty()) {
            return back()->withErrors(['topic' => 'Tidak ada topik terpilih yang menunggu keputusan.']);
        }
        $selected->each(fn (Topic $topic) => $topics->review($topic, false, $data['notes'], $request->user()));

        return back()->with('status', $selected->count().' topik ditolak.');
    }

    /** Tolak seluruh topik menunggu pada jenis topik ini yang ditandai kata kunci terlarang; alasan menyebut kata kuncinya. */
    public function rejectFlagged(Request $request, TopicService $topics): RedirectResponse
    {
        $data = $request->validate(['source' => ['required', Rule::in(array_keys(ForbiddenKeyword::SOURCES))]]);
        $period = Period::workspace();

        $flagged = Topic::query()->where('source', $data['source'])->where('status', 'menunggu')
            ->when($period, fn ($q) => $q->where('period_id', $period->id))->get()
            ->filter(fn (Topic $topic) => ! empty($topic->keyword_hits));
        if ($flagged->isEmpty()) {
            return back()->withErrors(['topic' => 'Tidak ada topik menunggu yang ditandai kata kunci terlarang.']);
        }
        $flagged->each(fn (Topic $topic) => $topics->review($topic, false,
            'Topik mengandung kata kunci terlarang: '.implode(', ', $topic->keyword_hits).'.', $request->user()));

        return back()->with('status', $flagged->count().' topik ber-flag kata kunci terlarang ditolak.');
    }

    /** Admin menambah topik ke bank topik dosen (langsung terbit) atas nama dosen pengusul. */
    public function storeLecturerTopic(Request $request, TopicService $topics): RedirectResponse
    {
        $period = Period::workspace() ?? abort(409, 'Buka dashboard periode terlebih dahulu.');
        $topic = $topics->saveByAdmin(null, $this->validatedLecturerTopic($request, $period->id, $topics), $request->user(), $period->id);

        return redirect()->route('admin.topics.index', ['tab' => 'dosen'])->with('status', "Topik “{$topic->title}” ditambahkan ke bank topik dosen.");
    }

    public function updateLecturerTopic(Request $request, Topic $topic, TopicService $topics): RedirectResponse
    {
        abort_unless($topic->source === 'dosen', 404);
        $topics->saveByAdmin($topic, $this->validatedLecturerTopic($request, $topic->period_id, $topics), $request->user(), $topic->period_id);

        return redirect()->route('admin.topics.index', ['tab' => 'dosen'])->with('status', "Topik “{$topic->title}” diperbarui.");
    }

    /** Admin dapat menghapus topik apa pun, termasuk yang sudah memiliki pelamar / tim diterima (lihat TopicService). */
    public function destroyLecturerTopic(Request $request, Topic $topic, TopicService $topics): RedirectResponse
    {
        abort_unless($topic->source === 'dosen', 404);
        $affected = $topics->deleteLecturerTopic($topic, $request->user());

        return back()->with('status', "Topik “{$topic->title}” dihapus dari bank topik dosen.".($affected ? " {$affected} tim terdampak sudah diberi notifikasi." : ''));
    }

    private function validatedLecturerTopic(Request $request, int $periodId, TopicService $topics): array
    {
        [$rules, $messages] = $topics->lecturerTopicRules($periodId);
        $rules['lecturer_id'] = ['required', 'integer', Rule::exists('users', 'id')->where('role', 'dosen')->where('is_active', true)];

        return $request->validate($rules, $messages);
    }

    /** Pilihan formulir topik dosen admin: prodi, peminatan periode, dan dosen (pengusul utama & bersama). */
    private function formData(?Period $period): array
    {
        return [
            'programs' => StudyProgram::query()->orderBy('name')->get(),
            'prodiSpecializations' => $period ? RegistrationRule::query()->where('period_id', $period->id)->with('specializations')->get()
                ->flatMap(fn (RegistrationRule $rule) => $rule->specializations->pluck('name'))->unique()->sort()->values() : collect(),
            'lecturers' => User::query()->where('role', 'dosen')->where('is_active', true)->with('lecturerProfile')->orderBy('name')->get(),
        ];
    }

    public function decide(Request $request, Topic $topic, TopicService $topics): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'notes' => ['nullable', 'required_if:decision,reject', 'string', 'max:2000'],
        ], ['notes.required_if' => 'Catatan wajib diisi saat menolak topik.']);

        if ($topic->status !== 'menunggu') {
            return back()->withErrors(['topic' => 'Topik ini sudah diputuskan.']);
        }

        $topics->review($topic, $data['decision'] === 'approve', $data['notes'] ?? null, $request->user());

        return back()->with('status', 'Keputusan topik disimpan.');
    }

    public function storeKeyword(Request $request, TopicService $topics): RedirectResponse
    {
        $data = $request->validate([
            'source' => ['required', Rule::in(array_keys(ForbiddenKeyword::SOURCES))],
            'keyword' => ['required', 'string', 'max:100'],
        ]);
        ForbiddenKeyword::query()->firstOrCreate(['source' => $data['source'], 'keyword' => Str::lower(trim($data['keyword']))]);
        // Topik yang sudah menunggu approval ikut diperiksa ulang, bukan hanya pengajuan berikutnya.
        $topics->refreshKeywordHits($data['source']);

        return back()->with('status', 'Kata kunci ditambahkan. Topik yang menunggu approval sudah diperiksa ulang.');
    }

    public function destroyKeyword(ForbiddenKeyword $keyword, TopicService $topics): RedirectResponse
    {
        $keyword->delete();
        $topics->refreshKeywordHits($keyword->source);

        return back()->with('status', 'Kata kunci dihapus.');
    }
}
