<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BranchLocation extends Model
{
    protected $fillable = [
        'name',
        'description',
        'latitude',
        'longitude',
        'page_url',
        'maps_url',
        'resolved_maps_url',
        'image_url',
        'rank',
        'source',
        'source_key',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'rank' => 'integer',
    ];
}


