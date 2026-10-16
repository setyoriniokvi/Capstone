<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReminderDispatch;
use App\Models\ReminderRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** F50 / F55 / F56 — konfigurasi pengingat otomatis dan riwayat pengiriman. */
class ReminderController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.reminders', [
            'rules' => ReminderRule::query()->orderBy('job_type')->orderByDesc('hours_before')->get(),
            'dispatches' => ReminderDispatch::query()->with('lecturer')
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
                ->when($request->filled('kanal'), fn ($q) => $q->where('channel', $request->string('kanal')))
                ->latest('id')->paginate(30)->withQueryString(),
            'automationReady' => filled(config('capstone.automation.token')),
            'webhookReady' => filled(config('capstone.automation.webhook_url')),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        ReminderRule::create($this->validated($request));

        return back()->with('status', 'Aturan pengingat ditambahkan.');
    }

    public function update(Request $request, ReminderRule $rule): RedirectResponse
    {
        $rule->update($this->validated($request, $rule));

        return back()->with('status', 'Aturan pengingat diperbarui.');
    }

    public function destroy(ReminderRule $rule): RedirectResponse
    {
        $rule->delete();

        return back()->with('status', 'Aturan pengingat dihapus.');
    }

    public function retry(ReminderDispatch $dispatch): RedirectResponse
    {
        if (! in_array($dispatch->status, ['gagal', 'dibatalkan'], true) || $dispatch->recipient === '') {
            return back()->withErrors(['dispatch' => 'Pengiriman ini tidak dapat diulang. Pastikan kontak dosen sudah terisi.']);
        }
        $dispatch->update(['status' => 'antre', 'attempts' => 0, 'last_error' => null]);

        return back()->with('status', 'Pengiriman dimasukkan kembali ke antrean.');
    }

    private function validated(Request $request, ?ReminderRule $rule = null): array
    {
        $data = $request->validate([
            'job_type' => ['required', Rule::in(array_keys(config('capstone.reminder_job_types')))],
            'hours_before' => ['required', 'integer', 'between:1,720', Rule::unique('reminder_rules')->where('job_type', $request->input('job_type'))->ignore($rule?->id)],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['in:email,whatsapp'],
        ], ['hours_before.unique' => 'Ambang waktu ini sudah ada untuk jenis pekerjaan tersebut.']);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
