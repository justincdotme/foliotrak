<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tag;
use App\Models\User;

/**
 * One household per install (D4): any authenticated user may act on any tag.
 * The policy is the seam where ownership checks would land if that ever changes.
 */
class TagPolicy
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
     * @param User $user
     * @param Tag  $tag
     *
     * @return boolean
     */
    public function view(User $user, Tag $tag): bool
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
     * @param User $user
     * @param Tag  $tag
     *
     * @return boolean
     */
    public function update(User $user, Tag $tag): bool
    {
        return true;
    }

    /**
     * @param User $user
     * @param Tag  $tag
     *
     * @return boolean
     */
    public function delete(User $user, Tag $tag): bool
    {
        return true;
    }
}
