<?php

namespace DDD\App\Services\RateGroups;

use DDD\Domain\Organizations\Organization;
use DDD\Domain\Rates\RateGroup;
use DDD\Domain\Columns\Column;
use DDD\Domain\Rates\Rate;
use Illuminate\Support\Facades\DB;

class RateGroupCloner
{
    /**
     * Clone a rate group and its columns/rates as a draft.
     *
     * @param Organization $organization
     * @param RateGroup $sourceGroup
     * @return RateGroup
     */
    public function cloneAsDraft(Organization $organization, RateGroup $sourceGroup): RateGroup
    {
        if ($sourceGroup->organization_id !== $organization->id) {
            abort(422, 'Source group does not belong to the organization.');
        }

        return DB::transaction(function () use ($organization, $sourceGroup) {
            $newGroup = RateGroup::create([
                'organization_id' => $organization->id,
                'user_id' => $sourceGroup->user_id,
                'title' => $sourceGroup->title,
                'published_at' => null,
                'revision_of' => $sourceGroup->id,
                'position' => $sourceGroup->position,
            ]);

            // Clone columns
            $sourceColumns = Column::where('organization_id', $organization->id)
                ->where('rate_group_id', $sourceGroup->id)
                ->get();

            foreach ($sourceColumns as $column) {
                Column::create([
                    'organization_id' => $organization->id,
                    'user_id' => $column->user_id,
                    'uid' => $column->uid,
                    'name' => $column->name,
                    'order' => $column->order,
                    'rate_group_id' => $newGroup->id,
                ]);
            }

            // Clone rates
            $sourceRates = Rate::where('organization_id', $organization->id)
                ->where('rate_group_id', $sourceGroup->id)
                ->get();

            foreach ($sourceRates as $rate) {
                Rate::create([
                    'organization_id' => $organization->id,
                    'user_id' => $rate->user_id,
                    'uid' => $rate->uid,
                    'rate_group_id' => $newGroup->id,
                    'data' => $rate->data,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $newGroup;
        });
    }
}
