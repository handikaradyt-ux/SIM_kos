<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Facility extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'location_type',
        'room_id',
        'area_name',
        'condition',
        'notes',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    /**
     * Scope query to only include active facilities.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    /**
     * Scope query to only include archived facilities.
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull('archived_at');
    }

    /**
     * Scope query to search by code, name, area_name, or room number.
     * Uses grouped OR conditions so it doesn't break archive/condition filters.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || trim($term) === '') {
            return $query;
        }

        $term = trim($term);

        return $query->where(function (Builder $q) use ($term) {
            $q->where('code', 'like', "%{$term}%")
              ->orWhere('name', 'like', "%{$term}%")
              ->orWhere('area_name', 'like', "%{$term}%")
              ->orWhereHas('room', function (Builder $rq) use ($term) {
                  $rq->where('number', 'like', "%{$term}%");
              });
        });
    }

    /**
     * Scope query to filter by condition.
     */
    public function scopeCondition(Builder $query, ?string $condition): Builder
    {
        if ($condition && in_array($condition, ['good', 'broken', 'repairing'], true)) {
            return $query->where('condition', $condition);
        }

        return $query;
    }

    /**
     * Scope query to filter by location type.
     */
    public function scopeLocationType(Builder $query, ?string $locationType): Builder
    {
        if ($locationType && in_array($locationType, ['room', 'shared'], true)) {
            return $query->where('location_type', $locationType);
        }

        return $query;
    }

    /**
     * Determine if facility is currently archived.
     */
    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Determine whether the facility has any historical complaint references.
     * Checks loaded attribute first to eliminate N+1 queries.
     */
    public function hasHistoricalReferences(): bool
    {
        if (array_key_exists('has_complaints', $this->attributes)) {
            return (bool) $this->attributes['has_complaints'];
        }

        return $this->complaints()->exists();
    }

    /**
     * Facility can be physically deleted only if no complaints exist.
     */
    public function canBeDeleted(): bool
    {
        return ! $this->hasHistoricalReferences();
    }

    /**
     * Determine whether the facility has open or in-progress complaints.
     * Checks loaded attribute first to eliminate N+1 queries.
     */
    public function hasActiveComplaints(): bool
    {
        if (array_key_exists('has_active_complaints', $this->attributes)) {
            return (bool) $this->attributes['has_active_complaints'];
        }

        return $this->complaints()->whereIn('status', ['open', 'in_progress'])->exists();
    }

    /**
     * Facility can be archived only if no active complaints exist.
     */
    public function canBeArchived(): bool
    {
        return ! $this->hasActiveComplaints();
    }

    /**
     * Human-readable label for location.
     */
    public function getLocationLabelAttribute(): string
    {
        if ($this->location_type === 'room') {
            return 'Kamar ' . ($this->room?->number ?? ($this->room_id ? "#{$this->room_id}" : '-'));
        }

        return 'Area Bersama: ' . ($this->area_name ?? '-');
    }

    /**
     * Human-readable label for condition in Indonesian.
     */
    public function getConditionLabelAttribute(): string
    {
        return match ($this->condition) {
            'good' => 'Baik',
            'broken' => 'Rusak',
            'repairing' => 'Dalam Perbaikan',
            default => ucfirst((string) $this->condition),
        };
    }

    /**
     * Bootstrap badge class for condition.
     */
    public function getConditionBadgeClassAttribute(): string
    {
        return match ($this->condition) {
            'good' => 'bg-success',
            'broken' => 'bg-danger',
            'repairing' => 'bg-warning text-dark',
            default => 'bg-secondary',
        };
    }
}
