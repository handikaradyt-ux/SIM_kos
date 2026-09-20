<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Relationship: All placement history for this room.
     */
    public function placements(): HasMany
    {
        return $this->hasMany(Placement::class);
    }

    /**
     * Relationship: Active placement for this room (ended_on IS NULL).
     */
    public function activePlacement(): HasOne
    {
        return $this->hasOne(Placement::class)->whereNull('ended_on');
    }

    /**
     * Relationship: All facilities associated with this room (including archived facilities).
     */
    public function facilities(): HasMany
    {
        return $this->hasMany(Facility::class);
    }

    /**
     * Check if the room is currently occupied.
     * Uses withExists/relation if available to prevent N+1 queries.
     */
    public function getIsOccupiedAttribute(): bool
    {
        if (array_key_exists('active_placement_exists', $this->attributes)) {
            return (bool) $this->attributes['active_placement_exists'];
        }

        if ($this->relationLoaded('activePlacement')) {
            return $this->activePlacement !== null;
        }

        return $this->activePlacement()->exists();
    }

    /**
     * Check if the room is archived.
     */
    public function getIsArchivedAttribute(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Scope: Search room by number or type with grouped OR condition.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (!empty($term)) {
            $query->where(function (Builder $q) use ($term) {
                $q->where('number', 'like', "%{$term}%")
                  ->orWhere('type', 'like', "%{$term}%");
            });
        }

        return $query;
    }

    /**
     * Scope: Only active rooms (not archived).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    /**
     * Scope: Only archived rooms.
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull('archived_at');
    }

    /**
     * Scope: Rooms with an active placement.
     */
    public function scopeOccupied(Builder $query): Builder
    {
        return $query->whereHas('placements', function (Builder $q) {
            $q->whereNull('ended_on');
        });
    }

    /**
     * Scope: Rooms without an active placement.
     */
    public function scopeVacant(Builder $query): Builder
    {
        return $query->whereDoesntHave('placements', function (Builder $q) {
            $q->whereNull('ended_on');
        });
    }

    /**
     * Check if room has historical references (placements or facilities).
     * Used to prevent physical DELETE when references exist.
     */
    public function hasHistoricalReferences(): bool
    {
        return $this->placements()->exists() || $this->facilities()->exists();
    }

    /**
     * Determine if room can be safely physically deleted.
     */
    public function canBeDeleted(): bool
    {
        return !$this->hasHistoricalReferences();
    }

    /**
     * Determine if room can be archived.
     * Blocked if room has an active placement.
     */
    public function canBeArchived(): bool
    {
        return !$this->is_occupied;
    }
}
