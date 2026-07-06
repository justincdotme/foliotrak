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
    /**
     * @param User $user
     *
     * @return boolean
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * @param User   $user
     * @param Sensor $sensor
     *
     * @return boolean
     */
    public function view(User $user, Sensor $sensor): bool
    {
        return true;
    }

    /**
     * @param User $user
     *
     * @return boolean
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * @param User   $user
     * @param Sensor $sensor
     *
     * @return boolean
     */
    public function update(User $user, Sensor $sensor): bool
    {
        return true;
    }

    /**
     * @param User   $user
     * @param Sensor $sensor
     *
     * @return boolean
     */
    public function delete(User $user, Sensor $sensor): bool
    {
        return true;
    }
}
