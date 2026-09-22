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
     * Determine whether the user can view the specific resident profile in master management.
     * Only Admin and Owner can view residents in the master section.
     * Resident role is strictly forbidden (HTTP 403), even when trying to access their own ID via master.
     */
    public function viewMaster(User $user, ?Resident $resident = null): bool
    {
        return in_array($user->role?->code, ['admin', 'owner'], true);
    }

    /**
     * Determine whether the user can delete the resident profile.
     * Only Admin can delete residents.
     */
    public function delete(User $user, Resident $resident): bool
    {
        return $user->role?->code === 'admin';
    }

    /**
     * Determine whether the user can archive the resident.
     * Only Admin can archive residents.
     */
    public function archive(User $user, Resident $resident): bool
    {
        return $user->role?->code === 'admin';
    }

    /**
     * Determine whether the user can unarchive the resident.
     * Only Admin can unarchive residents.
     */
    public function unarchive(User $user, Resident $resident): bool
    {
        return $user->role?->code === 'admin';
    }

    /**
     * Determine whether the user can activate the resident account.
     * Only Admin can activate resident accounts.
     */
    public function activate(User $user, Resident $resident): bool
    {
        return $user->role?->code === 'admin';
    }

    /**
     * Determine whether the user can deactivate the resident account.
     * Only Admin can deactivate resident accounts.
     */
    public function deactivate(User $user, Resident $resident): bool
    {
        return $user->role?->code === 'admin';
    }

    /**
     * Determine whether the user can reset the temporary password for resident account.
     * Only Admin can reset resident passwords.
     */
    public function resetPassword(User $user, Resident $resident): bool
    {
        return $user->role?->code === 'admin';
    }
}
