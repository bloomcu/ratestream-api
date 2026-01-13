<?php

namespace DDD\App\Traits;

use DDD\Domain\Organizations\Organization;
use DDD\Domain\Rates\RateGroup;

trait ResolvesRateGroup
{
    /**
     * Resolve a valid rate group for this organization, falling back to the default.
     *
     * @param Organization $organization
     * @param int|null $rateGroupId
     * @return int
     */
    protected function resolveRateGroupId(Organization $organization, ?int $rateGroupId): int
    {
        $groupId = $rateGroupId ?? $organization->default_rate_group_id;

        if (! $groupId) {
            abort(422, 'Rate group is required for this organization.');
        }

        $group = RateGroup::where('id', $groupId)
            ->where('organization_id', $organization->id)
            ->first();

        if (! $group) {
            abort(422, 'Invalid rate group for this organization.');
        }

        return $group->id;
    }
}
