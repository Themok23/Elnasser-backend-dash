<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MissionSubmission extends Model
{
    protected $fillable = [
        'mission_id',
        'user_id',
        'status',
        'proof_image_path',
        'note_user',
        'note_admin',
        'reviewed_by_admin_id',
        'reviewed_at',
        'approved_points',
        'awarded_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'awarded_at' => 'datetime',
        'approved_points' => 'integer',
    ];

    protected $appends = [
        'proof_image_url',
    ];

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by_admin_id');
    }

    public function getProofImageUrlAttribute(): ?string
    {
        if (empty($this->proof_image_path)) {
            return null;
        }
        return Storage::disk('public')->url($this->proof_image_path);
    }
}


