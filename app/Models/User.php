<?php

namespace App\Models;

use App\CentralLogics\Helpers;
use App\Models\BusinessSetting;
use App\Scopes\StoreScope;
use App\Scopes\ZoneScope;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\HasApiTokens;
use Modules\Rental\Entities\Trips;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
        'interest',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_phone_verified' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'order_count' => 'integer',
        'wallet_balance' => 'float',
        'loyalty_point' => 'integer',
        'ref_by' => 'integer',
        'tier' => 'string',
    ];
    protected $appends = ['image_full_url', 'tier_level'];
    public function getImageFullUrlAttribute(){
        $value = $this->image;
        if ($this->storage && is_iterable($this->storage) && count($this->storage) > 0) {
            foreach ($this->storage as $storage) {
                if (isset($storage['key']) && $storage['key'] == 'image') {
                    return Helpers::get_full_url('profile',$value,$storage['value']);
                }
            }
        }

        return Helpers::get_full_url('profile',$value,'public');
    }

    public function getFullNameAttribute(): string
    {
        return $this->f_name . ' ' . $this->l_name;
    }

    /**
     * Calculate tier level based on loyalty points
     */
    public function getTierLevelAttribute()
    {
        $points = $this->loyalty_point ?? 0;

        // Get tier thresholds from business settings
        $bronzeMax = (int) (BusinessSetting::where('key', 'tier_bronze_max_points')->first()->value ?? 100);
        $silverMin = (int) (BusinessSetting::where('key', 'tier_silver_min_points')->first()->value ?? 101);
        $silverMax = (int) (BusinessSetting::where('key', 'tier_silver_max_points')->first()->value ?? 500);
        $goldMin = (int) (BusinessSetting::where('key', 'tier_gold_min_points')->first()->value ?? 501);

        if ($points >= $goldMin) {
            return 'gold';
        } elseif ($points >= $silverMin && $points <= $silverMax) {
            return 'silver';
        } else {
            return 'bronze';
        }
    }

    /**
     * Update tier when points change
     */
    public function updateTier()
    {
        $newTier = $this->tier_level;
        if ($this->tier !== $newTier) {
            $this->tier = $newTier;
            // Use updateQuietly to avoid triggering model events
            $this->updateQuietly(['tier' => $newTier]);
        }
    }

    /**
     * Display name for a given tier key (bronze/silver/gold).
     * Tier display names are configurable in business settings:
     * - tier_bronze_name (default: Silver)
     * - tier_silver_name (default: Gold)
     * - tier_gold_name   (default: Platinum)
     */
    public static function tierDisplayName(?string $tier): string
    {
        $tier = $tier ?: 'bronze';

        $map = [
            'bronze' => 'tier_bronze_name',
            'silver' => 'tier_silver_name',
            'gold' => 'tier_gold_name',
        ];
        $key = $map[$tier] ?? null;
        if (!$key) {
            return ucfirst($tier);
        }

        $setting = BusinessSetting::where('key', $key)->first();
        $fallback = match ($tier) {
            'bronze' => 'Silver',
            'silver' => 'Gold',
            'gold' => 'Platinum',
            default => ucfirst($tier),
        };

        $value = trim((string) ($setting?->value ?? ''));
        return $value !== '' ? $value : $fallback;
    }

    public function scopeOfStatus($query, $status): void
    {
        $query->where('status', '=', $status);
    }

    public function orders()
    {
        return $this->hasMany(Order::class)->where('is_guest', 0);
    }
    public function trips()
    {
        return $this->hasMany(Trips::class)->where('is_guest', 0);
    }

    public function addresses(){
        return $this->hasMany(CustomerAddress::class);
    }

    public function userinfo()
    {
        return $this->hasOne(UserInfo::class,'user_id', 'id');
    }

    public function scopeZone($query, $zone_id=null){
        $query->when(is_numeric($zone_id), function ($q) use ($zone_id) {
            return $q->where('zone_id', $zone_id);
        });
    }

    public function storage()
    {
        return $this->morphMany(Storage::class, 'data');
    }

    protected static function booted()
    {
        static::addGlobalScope('storage', function ($builder) {
            $builder->with('storage');
        });
    }
    protected static function boot()
    {
        parent::boot();
        static::saved(function ($model) {
            if($model->isDirty('image')){
                $value = Helpers::getDisk();

                DB::table('storages')->updateOrInsert([
                    'data_type' => get_class($model),
                    'data_id' => $model->id,
                    'key' => 'image',
                ], [
                    'value' => $value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

    }
}
