<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ForbiddenKeyword;
use App\Models\Period;
use App\Models\StudyProgram;
use App\Models\Team;
use App\Models\Topic;
use App\Services\TopicService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** F7 / F8 — approval topik, kata kunci terlarang, rekap topik final. */
class TopicController extends Controller
{
    public function index(Request $request): View
    {
        $period = Period::active();
        $source = $request->input('sumber', 'mandiri') === 'dosen' ? 'dosen' : 'mandiri';
        $status = $request->input('status', 'menunggu');

        $topics = Topic::query()
            ->when($period, fn ($q) => $q->where('period_id', $period->id))
            ->where('source', $source)
            ->when($status !== 'semua', fn ($q) => $q->where('status', $status))
            ->with(['team', 'lecturer.lecturerProfile'])
            ->latest('updated_at')
            ->paginate(20)->withQueryString();

        return view('admin.topics.index', [
            'topics' => $topics,
            'source' => $source,
            'status' => $status,
            'keywords' => ForbiddenKeyword::query()->orderBy('keyword')->get(),
            'pending' => $period ? Topic::query()->where('period_id', $period->id)->where('status', 'menunggu')->selectRaw('source, count(*) as total')->groupBy('source')->pluck('total', 'source') : collect(),
        ]);
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

    /** F8 */
    public function recap(Request $request): View
    {
        $period = Period::active();
        $teams = Team::query()
            ->when($period, fn ($q) => $q->where('period_id', $period->id))
            ->where('status', 'diterima')
            ->when($request->filled('prodi'), fn ($q) => $q->where('prodi_code', $request->string('prodi')))
            ->with(['finalTopic.lecturer', 'independentTopic', 'supervisor.lecturer', 'examiner.lecturer'])
            ->orderBy('code')
            ->get();

        return view('admin.topics.recap', ['teams' => $teams, 'prodis' => StudyProgram::query()->orderBy('code')->get()]);
    }

    public function storeKeyword(Request $request): RedirectResponse
    {
        $data = $request->validate(['keyword' => ['required', 'string', 'max:100']]);
        ForbiddenKeyword::query()->firstOrCreate(['keyword' => Str::lower(trim($data['keyword']))]);

        return back()->with('status', 'Kata kunci ditambahkan.');
    }

    public function destroyKeyword(ForbiddenKeyword $keyword): RedirectResponse
    {
        $keyword->delete();

        return back()->with('status', 'Kata kunci dihapus.');
    }
}
