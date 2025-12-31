<?php

namespace DDD\Http\Rates;

use DDD\App\Controllers\Controller;
use DDD\Domain\Organizations\Organization;
use DDD\Domain\Rates\RateGroup;
use DDD\Http\Columns\Resources\ColumnResource;
use DDD\Http\Rates\Resources\RateGroupResource;
use DDD\Http\Rates\Resources\RateResource;

class RateGroupController extends Controller
{
    /**
     * List rate groups for an organization.
     *
     * @param Organization $organization
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Organization $organization)
    {
        $groups = RateGroup::with([
                'columns' => fn ($query) => $query->orderBy('order'),
                'rates',
            ])
            ->where('organization_id', $organization->id)
            
            ->orderBy('position')
            ->get();

        return $groups->map(function (RateGroup $group) {
            return [
                'group' => new RateGroupResource($group),
                'columns' => ColumnResource::collection($group->columns),
                'rates' => RateResource::collection($group->rates),
            ];
        });
    }
}
