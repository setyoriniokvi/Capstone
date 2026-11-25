<?php

namespace App\Notifications;

use App\Models\Team;
use App\Notifications\Concerns\DescribesTeamAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * F6 / F21 — email keputusan registrasi tim ke setiap anggota. Diterima: anggota yang akunnya dibuat ketua
 * (belum punya password) mendapat tombol "Atur password"; ketua & anggota lain cukup pemberitahuan.
 * Ditolak: semua anggota menerima informasi penolakan, alasannya, dan arahan registrasi ulang (tanpa tombol).
 * Kedua email mencantumkan nama & NIM anggota tim lainnya sebagai pengingat susunan tim.
 */
class TeamRegistrationDecision extends Notification
{
    use DescribesTeamAccount, Queueable;

    public function __construct(
        public Team $team,
        public bool $approved,
        public ?string $notes = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->greeting('Halo, '.$notifiable->name);

        if (! $this->approved) {
            $mail->subject('Registrasi tim '.$this->team->name.' ditolak')
                ->line("Registrasi tim {$this->team->name} ditolak oleh admin Capstone.")
                ->line('Alasan: '.($this->notes ?: 'tidak ada catatan dari admin.'));

            return $this->withTeammates($mail, $this->team, $notifiable)->line('Silakan lakukan registrasi ulang.');
        }

        $mail->subject('Tim '.$this->team->name.' diterima')
            ->line("Selamat! Registrasi tim {$this->team->name} telah diterima admin Capstone.");
        $this->withTeammates($mail, $this->team, $notifiable);

        if ($notifiable->awaitingActivation()) {
            $mail->line("Akun Anda sudah dibuat dengan email {$notifiable->email}. Atur password terlebih dahulu untuk masuk.");

            return $this->withPasswordSetup($mail, $notifiable);
        }

        return $mail->line('Langkah berikutnya: ajukan topik Capstone melalui aplikasi.');
    }
}
