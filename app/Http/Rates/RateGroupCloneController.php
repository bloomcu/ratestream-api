<?php

namespace DDD\Http\Rates;

use DDD\App\Controllers\Controller;
use DDD\Domain\Organizations\Organization;
use DDD\Domain\Rates\RateGroup;
use  DDD\App\Services\RateGroups\RateGroupCloner;
use DDD\Http\Columns\Resources\ColumnResource;
use DDD\Http\Rates\Resources\RateGroupResource;
use DDD\Http\Rates\Resources\RateResource;

class RateGroupCloneController extends Controller
{
    /**
     * Clone a rate group (and its rates/columns) as a draft.
     *
     * @param Organization $organization
     * @param RateGroup $rateGroup
     * @param RateGroupCloner $cloner
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(Organization $organization, RateGroup $rateGroup, RateGroupCloner $cloner)
    {
        // Ensure the group belongs to the org
        if ($rateGroup->organization_id !== $organization->id) {
            abort(422, 'Rate group does not belong to this organization.');
        }

        $newGroup = $cloner->cloneAsDraft($organization, $rateGroup);
        $newGroup->load([
            'columns' => fn ($query) => $query->orderBy('order'),
            'rates',
        ]);

        return response()->json([
            'message' => 'Rate group cloned.',
            'group' => new RateGroupResource($newGroup),
            'columns' => ColumnResource::collection($newGroup->columns),
            'rates' => RateResource::collection($newGroup->rates),
        ], 201);

        
    }
}
