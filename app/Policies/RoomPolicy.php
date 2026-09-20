<?php

namespace App\Policies;

use App\Models\Room;
use App\Models\User;

class RoomPolicy
{
    /**
     * Determine whether the user can view the room list.
     * Admin and Owner can view room list. Resident is forbidden (403).
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role?->code, ['admin', 'owner'], true);
    }

    /**
     * Determine whether the user can view the specific room details.
     * Admin and Owner can view room details. Resident is forbidden (403).
     */
    public function view(User $user, Room $room): bool
    {
        return in_array($user->role?->code, ['admin', 'owner'], true);
    }

    /**
     * Determine whether the user can create a room.
     * Only Admin can create rooms.
     */
    public function create(User $user): bool
    {
        return $user->role?->code === 'admin';
    }

    /**
     * Determine whether the user can update the room.
     * Only Admin can update rooms.
     */
    public function update(User $user, Room $room): bool
    {
        return $user->role?->code === 'admin';
    }

    /**
     * Determine whether the user can physically delete the room.
     * Only Admin can delete rooms.
     */
    public function delete(User $user, Room $room): bool
    {
        return $user->role?->code === 'admin';
    }

    /**
     * Determine whether the user can archive the room.
     * Only Admin can archive rooms.
     */
    public function archive(User $user, Room $room): bool
    {
        return $user->role?->code === 'admin';
    }

    /**
     * Determine whether the user can unarchive the room.
     * Only Admin can unarchive rooms.
     */
    public function unarchive(User $user, Room $room): bool
    {
        return $user->role?->code === 'admin';
    }
}
