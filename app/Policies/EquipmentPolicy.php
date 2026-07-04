<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Equipment;
use App\Models\User;

/**
 * One household per install (D4): any authenticated user may act on any equipment.
 * The policy is the seam where ownership checks would land if that ever changes.
 */
class EquipmentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Equipment $equipment): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Equipment $equipment): bool
    {
        return true;
    }

    public function delete(User $user, Equipment $equipment): bool
    {
        return true;
    }
}
