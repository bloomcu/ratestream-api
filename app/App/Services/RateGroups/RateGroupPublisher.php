<?php

namespace DDD\App\Services\RateGroups;

use DDD\Domain\Organizations\Organization;
use DDD\Domain\Rates\RateGroup;
use Illuminate\Support\Facades\DB;

class RateGroupPublisher
{
    /**
     * Publish a revision and archive the group it replaces.
     *
     * @param Organization $organization
     * @param RateGroup $revision
     * @return RateGroup
     */
    public function publish(Organization $organization, RateGroup $revision): RateGroup
    {
        if ($revision->organization_id !== $organization->id) {
            abort(422, 'Revision does not belong to the organization.');
        }

        if (! $revision->revision_of) {
            abort(422, 'Only revisions can be published.');
        }

        return DB::transaction(function () use ($organization, $revision) {
            $originalGroup = RateGroup::where('id', $revision->revision_of)
                ->where('organization_id', $organization->id)
                ->first();

            if (! $originalGroup) {
                abort(422, 'Original rate group not found.');
            }

            $originalGroup->archived_at = now();
            $originalGroup->superseded_by = $revision->id;
            $originalGroup->save();

            $revision->revision_of = null;
            $revision->published_at = $revision->published_at ?? now();
            $revision->archived_at = null;
            $revision->superseded_by = null;
            $revision->save();

            $organization->default_rate_group_id = $revision->id;
            $organization->save();

            return $revision->fresh();
        });
    }
}
