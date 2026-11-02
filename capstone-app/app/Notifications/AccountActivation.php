<?php

namespace App\Notifications;

use App\Models\Team;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * F21 — kirim ulang tautan atur password untuk anggota tim yang sudah diterima (tombol admin di detail tim).
 * Tautan berupa Signed URL tanpa kedaluwarsa; tidak berlaku lagi setelah password diatur.
 */
class AccountActivation extends Notification
{
    use Queueable;

    public function __construct(public Team $team) {}

    /** Tautan atur password untuk akun anggota — dipakai juga oleh email keputusan tim. */
    public static function url(User $user): string
    {
        return URL::signedRoute('password.setup', ['user' => $user->id]);
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Atur password akun Capstone Anda')
            ->greeting('Halo, '.$notifiable->name)
            ->line("Tim {$this->team->name} sudah diterima admin Capstone. Atur password untuk masuk dengan email {$notifiable->email}.");

        return TeamRegistrationDecision::withTeammates($mail, $this->team, $notifiable)
            ->action('Atur password', self::url($notifiable))
            ->line('Tautan ini dapat dipakai kapan saja sampai password Anda diatur.');
    }
}
