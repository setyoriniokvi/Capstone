<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPeriod;
use Illuminate\Database\Eloquent\Model;

/**
 * F50 / F52 — aturan reminder otomatis: jenis deadline (job_type, dan untuk penilaian juga bentuk penilaiannya),
 * berapa jam sebelum deadline dikirim, media (email / WhatsApp), dan pesan yang ditulis admin.
 */
class ReminderRule extends Model
{
    use BelongsToPeriod;

    /** Variabel yang dapat dipakai di pesan; diganti data dosen & pekerjaannya saat dikirim. */
    public const PLACEHOLDERS = [
        '{nama_dosen}' => 'nama dosen',
        '{daftar_tim}' => 'daftar tim/pekerjaan yang belum selesai',
        '{deadline}' => 'batas waktu',
        '{sisa_waktu}' => 'sisa waktu menuju deadline',
        '{tautan}' => 'tautan ke halaman terkait',
    ];

    /** Pesan untuk aturan lama (sebelum pesan dapat ditulis admin). */
    public const DEFAULT_MESSAGE = "Yth. {nama_dosen},\n\nPengingat Capstone, pekerjaan berikut belum diselesaikan:\n{daftar_tim}\n\nBatas waktu: {deadline} (sisa {sisa_waktu})\nBuka: {tautan}";

    protected $fillable = ['job_type', 'component', 'hours_before', 'channels', 'message', 'is_active'];

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Pilihan "Jenis deadline": penilaian per bentuk penilaian yang dinilai dosen, approval log bimbingan, approval pelamar topik.
     *
     * @return array<string, string> kunci "penilaian:{bentuk}" atau jenis pekerjaan → label
     */
    public static function deadlineOptions(): array
    {
        $options = collect(AssessmentComponent::lecturerConfig())
            ->mapWithKeys(fn ($c, $key) => ["penilaian:{$key}" => "Penilaian {$c['label']}"])
            ->all();

        return $options + collect(config('capstone.reminder_job_types'))->except('penilaian')->all();
    }

    public function deadlineKey(): string
    {
        return $this->component ? "{$this->job_type}:{$this->component}" : $this->job_type;
    }

    public function deadlineLabel(): string
    {
        return self::deadlineOptions()[$this->deadlineKey()]
            ?? ($this->component ? 'Penilaian '.$this->component : config("capstone.reminder_job_types.{$this->job_type}", $this->job_type));
    }

    /** "email,whatsapp" → "WhatsApp, Email" */
    public function channelLabel(): string
    {
        return collect(['whatsapp' => 'WhatsApp', 'email' => 'Email'])->only($this->channels ?? [])->implode(', ');
    }
}
