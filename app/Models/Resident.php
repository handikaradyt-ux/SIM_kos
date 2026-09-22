<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Resident extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'phone',
        'origin_address',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function placements(): HasMany
    {
        return $this->hasMany(Placement::class);
    }

    public function activePlacement(): HasOne
    {
        return $this->hasOne(Placement::class)->whereNull('ended_on');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('residents.archived_at');
    }

    public function scopeArchived($query)
    {
        return $query->whereNotNull('residents.archived_at');
    }

    public function scopeSearch($query, ?string $keyword)
    {
        if ($keyword !== null && trim($keyword) !== '') {
            $trimmed = trim($keyword);
            return $query->where(function ($q) use ($trimmed) {
                $q->where('residents.name', 'like', "%{$trimmed}%")
                  ->orWhere('residents.phone', 'like', "%{$trimmed}%")
                  ->orWhereHas('user', function ($uq) use ($trimmed) {
                      $uq->where('email', 'like', "%{$trimmed}%")
                        ->orWhere('name', 'like', "%{$trimmed}%");
                  });
            });
        }

        return $query;
    }

    public function getIsArchivedAttribute(): bool
    {
        return $this->archived_at !== null;
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function getHasHistoricalReferencesAttribute(): bool
    {
        if (isset($this->attributes['has_placements']) && isset($this->attributes['has_complaints'])) {
            return (bool) $this->attributes['has_placements'] || (bool) $this->attributes['has_complaints'];
        }

        if ($this->relationLoaded('placements') && $this->relationLoaded('user')) {
            $hasPlacements = $this->placements->isNotEmpty();
            $hasComplaints = $this->user && $this->user->relationLoaded('complaints') ? $this->user->complaints->isNotEmpty() : false;
            return $hasPlacements || $hasComplaints;
        }

        return $this->placements()->exists() || ($this->user && $this->user->complaints()->exists());
    }

    public function hasHistoricalReferences(): bool
    {
        return $this->has_historical_references;
    }

    public function canBeDeleted(): bool
    {
        return ! $this->hasHistoricalReferences();
    }

    public function canBeArchived(): bool
    {
        if ($this->relationLoaded('activePlacement')) {
            return $this->activePlacement === null;
        }

        if (isset($this->attributes['has_active_placement'])) {
            return ! (bool) $this->attributes['has_active_placement'];
        }

        return ! $this->activePlacement()->exists();
    }
}
