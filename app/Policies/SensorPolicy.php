<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Sensor;
use App\Models\User;

/**
 * One household per install: any authenticated user may act on any sensor.
 * The policy is the seam where ownership checks would land if that ever changes.
 */
class SensorPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Sensor $sensor): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Sensor $sensor): bool
    {
        return true;
    }

    public function delete(User $user, Sensor $sensor): bool
    {
        return true;
    }
}
