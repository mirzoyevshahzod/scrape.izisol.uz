<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class VehicleData extends Model
{
    use HasFactory;

    protected $table = 'vehicle_data';

    protected $fillable = [
        // Declarant ma'lumotlari
        'order_number',
        'queue_type',
        'reg_number',
        'registration_date',
        'status_changed',
        'declarant_status',
        'region',
        
        // Mintrans ma'lumotlari
        'rusumi',
        'yuk_qobiliyati',
        'license',
        'state_number',
        'company',
        'phone_number',
        'activity_type',
        'transport_type',
        'cargo_type',
        'issue_date',
        'expiry_date',
        'mintrans_status',
        'regional_office',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Accessors
    public function getFormattedCreatedAtAttribute()
    {
        return $this->created_at ? $this->created_at->format('d.m.Y H:i') : null;
    }

    public function getFormattedUpdatedAtAttribute()
    {
        return $this->updated_at ? $this->updated_at->format('d.m.Y H:i') : null;
    }

    // Scopes
    public function scopeByRegion($query, $region)
    {
        return $query->where('region', $region);
    }

    public function scopeActiveDeclarant($query)
    {
        return $query->whereNotNull('declarant_status')
                    ->where('declarant_status', '!=', '');
    }

    public function scopeActiveMintrans($query)
    {
        return $query->whereNotNull('mintrans_status')
                    ->where('mintrans_status', '!=', '');
    }

    public function scopeHasPhoneNumber($query)
    {
        return $query->whereNotNull('phone_number')
                    ->where('phone_number', '!=', '');
    }

    // Methods
    public function hasDeclarantData()
    {
        return !empty($this->order_number) || !empty($this->queue_type);
    }

    public function hasMintransData()
    {
        return !empty($this->rusumi) || !empty($this->license);
    }

    public function getFullDataStatus()
    {
        $declarant = $this->hasDeclarantData();
        $mintrans = $this->hasMintransData();
        
        if ($declarant && $mintrans) {
            return 'complete';
        } elseif ($declarant) {
            return 'declarant_only';
        } elseif ($mintrans) {
            return 'mintrans_only';
        } else {
            return 'empty';
        }
    }

    // Static methods
    public static function getStatistics()
    {
        return [
            'total' => self::count(),
            'with_declarant' => self::whereNotNull('order_number')->count(),
            'with_mintrans' => self::whereNotNull('rusumi')->count(),
            'with_phone' => self::whereNotNull('phone_number')
                               ->where('phone_number', '!=', '')
                               ->count(),
            'by_region' => self::groupBy('region')
                              ->selectRaw('region, count(*) as count')
                              ->pluck('count', 'region')
                              ->toArray(),
        ];
    }
}