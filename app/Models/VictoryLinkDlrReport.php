<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VictoryLinkDlrReport extends Model
{
    protected $table = 'victorylink_dlr_reports';

    protected $fillable = [
        'user_sms_id',
        'dlr_response_status',
        'payload',
        'received_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'received_at' => 'datetime',
    ];
}


