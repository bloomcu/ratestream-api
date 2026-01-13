<?php

namespace DDD\Http\Rates;

use DDD\App\Controllers\Controller;
use DDD\Domain\Organizations\Organization;
use DDD\Domain\Rates\RateGroup;
use DDD\Http\Rates\Resources\RateGroupResource;
use DDD\Http\Columns\Resources\ColumnResource;
use DDD\Http\Rates\Resources\RateResource;

class RateGroupRevisionController extends Controller
{
    /**
     * List revisions for a given rate group.
     *
     * @param Organization $organization
     * @param RateGroup $rateGroup
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Organization $organization, RateGroup $rateGroup)
    {
        if ($rateGroup->organization_id !== $organization->id) {
            abort(422, 'Rate group does not belong to this organization.');
        }

        $revisions = RateGroup::with([
                'columns' => fn ($query) => $query->orderBy('order'),
                'rates',
            ])
            ->where('organization_id', $organization->id)
            ->where('revision_of', $rateGroup->id)
            ->orderBy('position')
            ->get();

        return $revisions->map(function (RateGroup $group) {
            return [
                'group' => new RateGroupResource($group),
                'columns' => ColumnResource::collection($group->columns),
                'rates' => RateResource::collection($group->rates),
            ];
        });
    }
}
