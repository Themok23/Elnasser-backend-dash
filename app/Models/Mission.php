<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mission extends Model
{
    protected $fillable = [
        'name',
        'description',
        'points',
        'is_active',
        'requires_proof',
        'proof_instructions',
        'max_per_user',
        'start_at',
        'end_at',
        'created_by_admin_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'requires_proof' => 'boolean',
        'points' => 'integer',
        'max_per_user' => 'integer',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(MissionSubmission::class);
    }
}




