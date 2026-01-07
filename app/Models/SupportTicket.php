<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicket extends Model
{
    protected $fillable = [
        'ticket_number',
        'user_id',
        'support_ticket_type_id',
        'inquiry_type',
        'branch_location_id',
        'problem',
        'contact_email',
        'contact_phone',
        'callback_requested',
        'callback_time',
        'callback_notes',
        'status',
        'admin_notes',
        'created_by_admin_id',
        'resolved_at',
    ];

    protected $casts = [
        'callback_requested' => 'boolean',
        'callback_time' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(SupportTicketType::class, 'support_ticket_type_id');
    }

    public function branchLocation(): BelongsTo
    {
        return $this->belongsTo(BranchLocation::class, 'branch_location_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }
}


