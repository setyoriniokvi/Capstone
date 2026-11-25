<?php

namespace App\Services\Reminders;

use App\Models\ReminderDispatch;
use App\Services\ReminderService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * F51 / F53 / F55 — pengirim pengingat langsung dari aplikasi (REMINDER_SENDER=app), pengganti workflow n8n:
 * mengambil antrean yang sama (ReminderService::claim — ambang waktu, anti-duplikat, batal bila sudah selesai),
 * mengirim WhatsApp lewat Fonnte atau email lewat SMTP aplikasi, lalu mencatat hasilnya.
 */
class ReminderSender
{
    public function __construct(private readonly ReminderService $reminders, private readonly FonnteClient $fonnte) {}

    public function enabled(): bool
    {
        return config('capstone.automation.sender') === 'app';
    }

    /** @return array{sent: int, failed: int} */
    public function sendQueued(int $limit = 50): array
    {
        $result = ['sent' => 0, 'failed' => 0];

        foreach ($this->reminders->claim($limit) as $item) {
            $dispatch = ReminderDispatch::query()->find($item['id']);
            if (! $dispatch) {
                continue;
            }

            try {
                if ($item['channel'] === 'whatsapp') {
                    $this->fonnte->send($item['recipient'], $item['message']);
                } else {
                    Mail::raw($item['message'], fn ($mail) => $mail->to($item['recipient'])->subject($item['subject']));
                }
                $this->reminders->report($dispatch, true);
                $result['sent']++;
            } catch (Throwable $e) {
                $this->reminders->report($dispatch, false, Str::limit($e->getMessage(), 500));
                $result['failed']++;
            }
        }

        return $result;
    }
}
