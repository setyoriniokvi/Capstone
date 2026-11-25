<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Period;
use App\Models\ReminderDispatch;
use App\Support\Sorting;
use App\Models\ReminderRule;
use App\Models\User;
use App\Services\Reminders\FonnteClient;
use App\Services\Reminders\ReminderSender;
use App\Services\ReminderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Support\PeriodContext;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * F50 / F52 / F55 / F56 — Reminder Otomatis: aturan per jenis deadline (jam sebelum deadline, media, pesan tulisan admin),
 * kirim manual ke semua dosen yang belum menilai, uji kirim WhatsApp/email, dan riwayat pengiriman.
 */
class ReminderController extends Controller
{
    public function index(Request $request, ReminderSender $sender, FonnteClient $fonnte): View
    {
        $options = ReminderRule::deadlineOptions();
        $order = array_flip(array_keys($options));

        return view('admin.reminders', [
            // Urut jenis deadline (urutan bentuk penilaian), lalu jam terbesar
            'rules' => ReminderRule::query()->get()
                ->sortBy(fn (ReminderRule $r) => [$order[$r->deadlineKey()] ?? PHP_INT_MAX, -$r->hours_before])->values(),
            'deadlineOptions' => $options,
            'dispatches' => $this->dispatches($request),
            'automationReady' => filled(config('capstone.automation.token')),
            'appSender' => $sender->enabled(),
            'fonnteReady' => $fonnte->configured(),
        ]);
    }

    /** Riwayat pengiriman (log): bawaan Waktu turun (terbaru di atas). */
    private function dispatches(Request $request)
    {
        // Log pengiriman periode yang sedang dibuka (beberapa periode boleh aktif bersamaan).
        $query = ReminderDispatch::query()->where('period_id', Period::workspace()?->id)->with('lecturer')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('kanal'), fn ($q) => $q->where('channel', $request->string('kanal')))
            // Cari: nama / email dosen, penerima (nomor WA / email), atau isi pekerjaan
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.trim((string) $request->string('q')).'%';
                $q->where(fn ($w) => $w->where('recipient', 'like', $term)->orWhere('payload', 'like', $term)
                    ->orWhereHas('lecturer', fn ($u) => $u->where('name', 'like', $term)->orWhere('email', 'like', $term)));
            });
        Sorting::apply($query, $request, [
            'waktu' => 'created_at',
            'nama' => fn ($q, $dir) => $q->orderBy(\App\Models\User::query()->select('name')->whereColumn('users.id', 'reminder_dispatches.lecturer_id')->limit(1), $dir),
            'kategori' => 'status',
        ], 'waktu', 'desc');

        return $query->paginate(30)->withQueryString();
    }

    public function store(Request $request): RedirectResponse
    {
        ReminderRule::create($this->validated($request) + ['is_active' => true]);

        return back()->with('status', 'Reminder otomatis ditambahkan.');
    }

    public function update(Request $request, ReminderRule $rule): RedirectResponse
    {
        $rule->update($this->validated($request, $rule));

        return back()->with('status', 'Reminder otomatis diperbarui.');
    }

    /** Aktifkan / nonaktifkan; reminder nonaktif tidak dikirim. */
    public function toggle(ReminderRule $rule): RedirectResponse
    {
        $rule->update(['is_active' => ! $rule->is_active]);

        return back()->with('status', 'Reminder otomatis '.($rule->is_active ? 'diaktifkan.' : 'dinonaktifkan.'));
    }

    public function destroy(ReminderRule $rule): RedirectResponse
    {
        $rule->delete();

        return back()->with('status', 'Reminder otomatis dihapus.');
    }

    /** Kirim reminder manual (WhatsApp & email) ke semua dosen yang masih punya penilaian belum lengkap. */
    public function sendManual(Request $request, ReminderService $reminders, ReminderSender $sender): RedirectResponse
    {
        $period = Period::workspace();
        $lecturerIds = $period ? $reminders->incompleteAssessments($period)->where('done', false)->pluck('lecturer.id')->unique() : collect();
        if ($lecturerIds->isEmpty()) {
            return back()->with('status', 'Semua dosen sudah lengkap menilai; tidak ada reminder yang dikirim.');
        }

        $count = $reminders->queueManual(User::query()->whereIn('id', $lecturerIds)->get(), ['whatsapp', 'email'], $request->user());
        if (! $sender->enabled()) {
            return back()->with('status', "{$count} reminder untuk {$lecturerIds->count()} dosen masuk antrean workflow n8n.");
        }
        $result = $sender->sendQueued();

        return back()->with('status', "Reminder manual untuk {$lecturerIds->count()} dosen: {$result['sent']} terkirim"
            .($result['failed'] ? ", {$result['failed']} gagal (lihat Riwayat pengiriman)" : '').'.');
    }

    /** Uji kirim pesan ke nomor WhatsApp dan/atau email (minimal salah satu). */
    public function test(Request $request, FonnteClient $fonnte, ReminderService $reminders): RedirectResponse
    {
        $data = $request->validate([
            'test_phone' => ['nullable', 'required_without:test_email', 'string', 'max:20', 'regex:/^[0-9+\s-]{8,20}$/'],
            'test_email' => ['nullable', 'required_without:test_phone', 'email', 'max:255'],
            'test_message' => ['required', 'string', 'max:2000'],
        ], [
            'test_phone.required_without' => 'Isi nomor WhatsApp atau alamat email uji.',
            'test_email.required_without' => 'Isi nomor WhatsApp atau alamat email uji.',
            'test_phone.regex' => 'Nomor WhatsApp hanya berisi angka, mis. 081234567890.',
            'test_message.required' => 'Pesan uji wajib diisi.',
        ]);

        $sent = [];
        $errors = [];
        if (filled($data['test_phone'] ?? null)) {
            $target = $reminders->normalizePhone($data['test_phone']);
            try {
                $fonnte->send($target, $data['test_message']);
                $sent[] = "WhatsApp {$target}";
            } catch (Throwable $e) {
                $errors['test_phone'] = $e->getMessage();
            }
        }
        if (filled($data['test_email'] ?? null)) {
            try {
                Mail::raw($data['test_message'], fn ($mail) => $mail->to($data['test_email'])->subject('[Capstone] Uji reminder'));
                $sent[] = "email {$data['test_email']}";
            } catch (Throwable $e) {
                $errors['test_email'] = 'Email gagal dikirim: '.$e->getMessage();
            }
        }

        $response = back()->withInput();
        if ($errors) {
            $response->withErrors($errors);
        }

        return $sent ? $response->with('status', 'Pesan uji terkirim ke '.implode(' dan ', $sent).'.') : $response;
    }

    public function retry(ReminderDispatch $dispatch, ReminderSender $sender): RedirectResponse
    {
        if (! in_array($dispatch->status, ['gagal', 'dibatalkan'], true) || $dispatch->recipient === '') {
            return back()->withErrors(['dispatch' => 'Pengiriman ini tidak dapat diulang. Pastikan kontak dosen sudah terisi.']);
        }
        $dispatch->update(['status' => 'antre', 'attempts' => 0, 'last_error' => null]);
        if (! $sender->enabled()) {
            return back()->with('status', 'Pengiriman dimasukkan kembali ke antrean.');
        }
        $result = $sender->sendQueued();

        return back()->with('status', $result['failed'] ? 'Pengiriman ulang gagal. Lihat kolom Status.' : 'Pengiriman ulang berhasil.');
    }

    /** Isian Tambah / Ubah: jenis deadline (penilaian per bentuk penilaian), jam sebelum deadline, media, pesan — wajib semua. */
    private function validated(Request $request, ?ReminderRule $rule = null): array
    {
        $request->validate([
            'deadline' => ['required', Rule::in(array_keys(ReminderRule::deadlineOptions()))],
        ], ['deadline.required' => 'Pilih jenis deadline.']);
        [$jobType, $component] = array_pad(explode(':', $request->input('deadline'), 2), 2, null);

        $data = $request->validate([
            'hours_before' => ['required', 'integer', 'between:1,720', Rule::unique('reminder_rules')
                ->where('period_id', PeriodContext::id())->where('job_type', $jobType)->where('component', $component)->ignore($rule?->id)],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['in:email,whatsapp'],
            'message' => ['required', 'string', 'max:2000'],
        ], [
            'hours_before.unique' => 'Reminder dengan jenis deadline dan jam ini sudah ada.',
            'channels.required' => 'Pilih minimal satu media.',
            'message.required' => 'Pesan wajib diisi.',
        ]);

        return ['job_type' => $jobType, 'component' => $component] + $data;
    }
}
