<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Plant;
use App\Models\User;

/**
 * One household per install (D4): any authenticated user may act on any plant.
 * The policy is the seam where ownership checks would land if that ever changes.
 */
class PlantPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Plant $plant): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Plant $plant): bool
    {
        return true;
    }

    public function delete(User $user, Plant $plant): bool
    {
        return true;
    }
}
