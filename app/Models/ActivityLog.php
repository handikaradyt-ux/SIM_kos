<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'actor_id',
        'actor_name',
        'action',
        'module',
        'entity_type',
        'entity_id',
        'entity_label',
        'summary',
        'changes',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Get the occurred_at timestamp converted to Asia/Jakarta timezone for display.
     */
    public function occurredAtJakarta(): \Illuminate\Support\Carbon
    {
        return $this->occurred_at->timezone('Asia/Jakarta');
    }
}
