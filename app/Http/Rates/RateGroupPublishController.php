<?php

namespace DDD\Http\Rates;

use DDD\App\Controllers\Controller;
use DDD\App\Services\RateGroups\RateGroupPublisher;
use DDD\Domain\Organizations\Organization;
use DDD\Domain\Rates\RateGroup;
use DDD\Http\Columns\Resources\ColumnResource;
use DDD\Http\Rates\Resources\RateGroupResource;
use DDD\Http\Rates\Resources\RateResource;

class RateGroupPublishController extends Controller
{
    /**
     * Publish a rate group revision.
     *
     * @param Organization $organization
     * @param RateGroup $rateGroup
     * @param RateGroupPublisher $publisher
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(
        Organization $organization,
        RateGroup $rateGroup,
        RateGroupPublisher $publisher
    ) {
        if ($rateGroup->organization_id !== $organization->id) {
            abort(422, 'Rate group does not belong to this organization.');
        }

        $publishedGroup = $publisher->publish($organization, $rateGroup);
        $publishedGroup->load([
            'columns' => fn ($query) => $query->orderBy('order'),
            'rates',
        ]);

        return response()->json([
            'message' => 'Rate group published.',
            'group' => new RateGroupResource($publishedGroup),
            'columns' => ColumnResource::collection($publishedGroup->columns),
            'rates' => RateResource::collection($publishedGroup->rates),
        ], 200);
    }
}
