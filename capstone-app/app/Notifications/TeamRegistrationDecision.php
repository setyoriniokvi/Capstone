<?php

namespace App\Notifications;

use App\Models\Team;
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
    use Queueable;

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

            return self::withTeammates($mail, $this->team, $notifiable)->line('Silakan lakukan registrasi ulang.');
        }

        $mail->subject('Tim '.$this->team->name.' diterima')
            ->line("Selamat! Registrasi tim {$this->team->name} telah diterima admin Capstone.");
        self::withTeammates($mail, $this->team, $notifiable);

        if ($notifiable->awaitingActivation()) {
            return $mail->line("Akun Anda sudah dibuat dengan email {$notifiable->email}. Atur password terlebih dahulu untuk masuk.")
                ->action('Atur password', AccountActivation::url($notifiable))
                ->line('Tautan ini dapat dipakai kapan saja sampai password Anda diatur.');
        }

        return $mail->line('Langkah berikutnya: ajukan topik Capstone melalui aplikasi.');
    }

    /** Pengingat susunan tim: nama & NIM anggota lain (ketua ditandai), tanpa penerima email itu sendiri. */
    public static function withTeammates(MailMessage $mail, Team $team, object $recipient): MailMessage
    {
        $others = $team->members()->with('user')->get()->reject(fn ($m) => $m->user_id === $recipient->id);
        if ($others->isEmpty()) {
            return $mail;
        }

        $mail->line('Anggota tim lainnya:');
        foreach ($others as $member) {
            $mail->line('• '.$member->user->name.' — NIM '.($member->user->nim ?? '-').($member->is_leader ? ' (Ketua)' : ''));
        }

        return $mail;
    }
}
