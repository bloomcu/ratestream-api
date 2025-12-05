<?php

namespace DDD\App\Traits;

use DDD\Domain\Base\Organizations\Organization;
use DDD\Domain\Rates\RateGroup;

trait EnsuresDefaultRateGroup
{
    /**
     * Ensure the organization has a default rate group and pointer set.
     *
     * @param Organization $organization
     * @param int|null $userId
     * @return RateGroup
     */
    protected function ensureDefaultRateGroup(Organization $organization, ?int $userId = null): RateGroup
    {
        $group = RateGroup::firstOrCreate(
            [
                'organization_id' => $organization->id,
                'title' => 'Default',
            ],
            [
                'user_id' => $userId,
                'published_at' => now(),
                'revision_of' => null,
                'position' => 1,
            ]
        );

        // If group exists without a user and we have one, backfill it.
        if ($userId && is_null($group->user_id)) {
            $group->user_id = $userId;
            $group->save();
        }

        if (! $organization->default_rate_group_id) {
            $organization->default_rate_group_id = $group->id;
            $organization->save();
        }

        return $group;
    }
}
