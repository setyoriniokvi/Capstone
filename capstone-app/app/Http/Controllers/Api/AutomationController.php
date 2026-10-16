<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReminderDispatch;
use App\Services\ReminderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * F57 — endpoint untuk workflow n8n.
 *  POST /api/automation/reminders/claim          → daftar pesan siap kirim
 *  POST /api/automation/reminders/{id}/report    → hasil pengiriman
 */
class AutomationController extends Controller
{
    public function claim(Request $request, ReminderService $reminders): JsonResponse
    {
        $items = $reminders->claim(min(100, max(1, $request->integer('limit', 50))));

        return response()->json(['count' => $items->count(), 'data' => $items->values()]);
    }

    public function report(Request $request, ReminderDispatch $dispatch, ReminderService $reminders): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:terkirim,gagal'],
            'error' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($dispatch->status !== 'diproses') {
            return response()->json(['message' => 'Pengiriman ini tidak sedang diproses.', 'status' => $dispatch->status], 409);
        }

        $reminders->report($dispatch, $data['status'] === 'terkirim', $data['error'] ?? null);

        return response()->json(['id' => $dispatch->id, 'status' => $dispatch->fresh()->status]);
    }
}
