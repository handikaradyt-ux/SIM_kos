<?php

namespace App\Policies;

use App\Models\Resident;
use App\Models\User;

class ResidentPolicy
{
    /**
     * Determine whether the user can view any resident models.
     * Admin and Owner can view the list of residents.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role?->code, ['admin', 'owner'], true);
    }

    /**
     * Determine whether the user can view the specific resident profile.
     * Admin and Owner can view any resident.
     * Resident can ONLY view their own profile.
     * Users without a resident profile are handled safely without 500 errors.
     */
    public function view(User $user, Resident $resident): bool
    {
        $role = $user->role?->code;

        if (in_array($role, ['admin', 'owner'], true)) {
            return true;
        }

        if ($role === 'resident') {
            // Safely verify ownership without null pointer exception if resident profile is missing
            $userResident = $user->resident;

            return $userResident !== null && $userResident->id === $resident->id;
        }

        return false;
    }

    /**
     * Determine whether the user can create residents.
     * Only Admin can create residents.
     */
    public function create(User $user): bool
    {
        return $user->role?->code === 'admin';
    }

    /**
     * Determine whether the user can update the resident profile.
     * Only Admin can update resident profiles.
     */
    public function update(User $user, Resident $resident): bool
    {
        return $user->role?->code === 'admin';
    }

    /**
     * Determine whether the user can delete/archive the resident.
     * Only Admin can delete/archive residents.
     */
    public function delete(User $user, Resident $resident): bool
    {
        return $user->role?->code === 'admin';
    }
}
