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
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Tag $tag): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Tag $tag): bool
    {
        return true;
    }

    public function delete(User $user, Tag $tag): bool
    {
        return true;
    }
}
