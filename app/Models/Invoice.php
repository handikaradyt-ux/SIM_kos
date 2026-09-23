<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'placement_id',
        'period_month',
        'due_on',
        'amount',
        'resident_name_snapshot',
        'room_number_snapshot',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'period_month' => 'date',
            'due_on' => 'date',
            'amount' => 'integer',
        ];
    }

    public function placement(): BelongsTo
    {
        return $this->belongsTo(Placement::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function validPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->where('status', 'valid');
    }

    /**
     * Check if this invoice has an associated valid payment.
     */
    public function hasValidPayment(): bool
    {
        if ($this->relationLoaded('validPayment')) {
            return $this->validPayment !== null;
        }

        return $this->validPayment()->exists();
    }
}
