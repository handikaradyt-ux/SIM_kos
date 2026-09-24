<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Placement extends Model
{
    use HasFactory;

    protected $fillable = [
        'resident_id',
        'room_id',
        'started_on',
        'ended_on',
        'agreed_monthly_rate',
        'created_by',
        'ended_by',
        'end_reason',
    ];

    protected function casts(): array
    {
        return [
            'started_on' => 'date',
            'ended_on' => 'date',
            'agreed_monthly_rate' => 'integer',
        ];
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function ender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ended_by');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    public function isActive(): bool
    {
        return $this->ended_on === null;
    }

    public function isEnded(): bool
    {
        return $this->ended_on !== null;
    }

    public function scopeActive($query)
    {
        return $query->whereNull('placements.ended_on');
    }

    public function scopeEnded($query)
    {
        return $query->whereNotNull('placements.ended_on');
    }
}
