<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PeerReview extends Model
{
    protected $fillable = ['team_id', 'reviewer_id', 'reviewee_id', 'aspect', 'score'];

    public function reviewee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewee_id');
    }
}
