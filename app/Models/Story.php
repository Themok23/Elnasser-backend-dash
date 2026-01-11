<?php

namespace App\Models;

use App\CentralLogics\Helpers;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;

class Story extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'type',
        'media_url',
        'description',
        'is_active',
        'created_by_admin_id',
        'expires_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = ['media_full_url'];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function views(): HasMany
    {
        return $this->hasMany(StoryView::class);
    }

    public function storage(): MorphMany
    {
        return $this->morphMany(Storage::class, 'data');
    }

    public function getMediaFullUrlAttribute()
    {
        $value = $this->media_url;
        if ($this->storage && $this->storage->count() > 0) {
            foreach ($this->storage as $storage) {
                if ($storage['key'] == 'media_url') {
                    return Helpers::get_full_url('story', $value, $storage['value']);
                }
            }
        }

        return Helpers::get_full_url('story', $value, 'public');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    protected static function boot()
    {
        parent::boot();
        
        static::addGlobalScope('storage', function ($builder) {
            $builder->with('storage');
        });

        static::saved(function ($model) {
            if ($model->isDirty('media_url')) {
                $value = Helpers::getDisk();

                DB::table('storages')->updateOrInsert([
                    'data_type' => get_class($model),
                    'data_id' => $model->id,
                    'key' => 'media_url',
                ], [
                    'value' => $value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }
}

