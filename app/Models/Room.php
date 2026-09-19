<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'number',
        'type',
        'monthly_rate',
        'notes',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'monthly_rate' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    public function placements(): HasMany
    {
        return $this->hasMany(Placement::class);
    }

    public function activePlacement(): HasOne
    {
        return $this->hasOne(Placement::class)->whereNull('ended_on');
    }

    public function facilities(): HasMany
    {
        return $this->hasMany(Facility::class);
    }
}
