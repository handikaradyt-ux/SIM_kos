<?php

namespace App\Policies;

use App\Models\Facility;
use App\Models\User;

class FacilityPolicy
{
    /**
     * Determine whether the user can view the facility list.
     * Admin and Owner can view facility list. Resident is forbidden (403).
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role?->code, ['admin', 'owner'], true);
    }

    /**
     * Determine whether the user can view the specific facility details.
     * Admin and Owner can view facility details. Resident is forbidden (403).
     */
    public function view(User $user, Facility $facility): bool
    {
        return in_array($user->role?->code, ['admin', 'owner'], true);
    }

    /**
     * Determine whether the user can create a facility.
     * Only Admin can create facilities.
     */
    public function create(User $user): bool
    {
        return $user->role?->code === 'admin';
    }

    /**
     * Determine whether the user can update the facility.
     * Only Admin can update facilities.
     */
    public function update(User $user, Facility $facility): bool
    {
        return $user->role?->code === 'admin';
    }

    /**
     * Determine whether the user can physically delete the facility.
     * Only Admin can delete facilities.
     */
    public function delete(User $user, Facility $facility): bool
    {
        return $user->role?->code === 'admin';
    }

    /**
     * Determine whether the user can archive the facility.
     * Only Admin can archive facilities.
     */
    public function archive(User $user, Facility $facility): bool
    {
        return $user->role?->code === 'admin';
    }

    /**
     * Determine whether the user can unarchive the facility.
     * Only Admin can unarchive facilities.
     */
    public function unarchive(User $user, Facility $facility): bool
    {
        return $user->role?->code === 'admin';
    }
}
