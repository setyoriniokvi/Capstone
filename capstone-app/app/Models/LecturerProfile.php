<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LecturerProfile extends Model
{
    protected $fillable = [
        'user_id', 'lecturer_code', 'nip', 'specialization',
        'can_propose_topic', 'can_supervise', 'can_examine', 'is_class_lecturer',
    ];

    protected function casts(): array
    {
        return [
            'can_propose_topic' => 'boolean',
            'can_supervise' => 'boolean',
            'can_examine' => 'boolean',
            'is_class_lecturer' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): string
    {
        return $this->is_class_lecturer ? 'dosen_kelas' : 'non_pengampu';
    }

    /** @return list<string> */
    public function accessLabels(): array
    {
        return array_keys(array_filter([
            'Pengusul topik' => $this->can_propose_topic,
            'Pembimbing' => $this->can_supervise,
            'Penguji' => $this->can_examine,
            'Dosen kelas' => $this->is_class_lecturer,
        ]));
    }
}
