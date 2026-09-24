<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    /**
     * Determine whether the user can view the invoice list.
     * Admin, Owner, and active Resident can view the invoices list (with role-based scoping).
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role?->code, ['admin', 'owner', 'resident'], true) && (bool) $user->is_active;
    }

    /**
     * Determine whether the user can view a specific invoice.
     * Admin and Owner can view any invoice.
     * Resident can ONLY view invoices belonging to their own placements (anti-IDOR).
     */
    public function view(User $user, Invoice $invoice): bool
    {
        if (! (bool) $user->is_active) {
            return false;
        }

        $role = $user->role?->code;

        if (in_array($role, ['admin', 'owner'], true)) {
            return true;
        }

        if ($role === 'resident') {
            $resident = $user->resident;

            if (! $resident) {
                return false;
            }

            // Ensure placement relation is available
            $placement = $invoice->placement ?? $invoice->load('placement')->placement;

            return $placement !== null && (int) $placement->resident_id === (int) $resident->id;
        }

        return false;
    }

    /**
     * Determine whether the user can preview invoice synchronization.
     * Strictly restricted to active Administrators.
     */
    public function preview(User $user): bool
    {
        return $user->role?->code === 'admin' && (bool) $user->is_active;
    }

    /**
     * Determine whether the user can execute invoice synchronization.
     * Strictly restricted to active Administrators.
     */
    public function sync(User $user): bool
    {
        return $user->role?->code === 'admin' && (bool) $user->is_active;
    }
}
