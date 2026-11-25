<?php

namespace App\Notifications\Concerns;

use App\Models\Team;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;

/**
 * Bagian email yang dipakai bersama notifikasi akun & tim (AccountActivation, TeamRegistrationDecision): tautan atur
 * password dan pengingat susunan tim. Dipisah ke trait agar kedua notifikasi tidak saling bergantung.
 */
trait DescribesTeamAccount
{
    /** Tautan atur password akun anggota: Signed URL tanpa kedaluwarsa, tidak berlaku lagi setelah password diatur. */
    public static function passwordSetupUrl(User $user): string
    {
        return URL::signedRoute('password.setup', ['user' => $user->id]);
    }

    /** Pengingat susunan tim: nama & NIM anggota lain (ketua ditandai), tanpa penerima email itu sendiri. */
    protected function withTeammates(MailMessage $mail, Team $team, object $recipient): MailMessage
    {
        $others = $team->members()->with('user')->get()->reject(fn ($m) => $m->user_id === $recipient->id);
        if ($others->isEmpty()) {
            return $mail;
        }

        $mail->line('Anggota tim lainnya:');
        foreach ($others as $member) {
            $mail->line('• '.$member->user->name.' - NIM '.($member->user->nim ?? '-').($member->is_leader ? ' (Ketua)' : ''));
        }

        return $mail;
    }

    /** Tombol atur password beserta keterangannya. */
    protected function withPasswordSetup(MailMessage $mail, User $user): MailMessage
    {
        return $mail->action('Atur password', self::passwordSetupUrl($user))
            ->line('Tautan ini dapat dipakai kapan saja sampai password Anda diatur.');
    }
}
