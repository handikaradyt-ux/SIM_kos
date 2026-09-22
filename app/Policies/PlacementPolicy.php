<?php

namespace App\Policies;

use App\Models\Placement;
use App\Models\User;

class PlacementPolicy
{
    /**
     * Determine whether the user can view the placement list.
     * Admin and Owner can view placements. Resident is forbidden (403).
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role?->code, ['admin', 'owner'], true) && (bool) $user->is_active;
    }

    /**
     * Determine whether the user can view the specific placement details.
     * Admin and Owner can view details. Resident is forbidden (403).
     */
    public function view(User $user, Placement $placement): bool
    {
        return in_array($user->role?->code, ['admin', 'owner'], true) && (bool) $user->is_active;
    }

    /**
     * Determine whether the user can create/start a placement.
     * Only Admin can start placements.
     */
    public function create(User $user): bool
    {
        return $user->role?->code === 'admin' && (bool) $user->is_active;
    }
}
