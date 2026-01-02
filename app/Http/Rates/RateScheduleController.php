<?php

namespace DDD\Http\Rates;

use DDD\App\Controllers\Controller;
use DDD\Domain\Organizations\Organization;
use DDD\Domain\Rates\RateGroup;
use DDD\Http\Columns\Resources\ColumnResource;
use DDD\Http\Rates\Resources\RateGroupResource;
use DDD\Http\Rates\Resources\RateResource;
use Illuminate\Http\Request;

class RateScheduleController extends Controller
{
    /**
     * Schedule a rate group revision for publishing.
     *
     * @param Organization $organization
     * @param RateGroup $rateGroup
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(Organization $organization, RateGroup $rateGroup, Request $request)
    {
        if ($rateGroup->organization_id !== $organization->id) {
            abort(422, 'Rate group does not belong to this organization.');
        }

        if (! $rateGroup->revision_of) {
            abort(422, 'Only revisions can be scheduled.');
        }

        $validated = $request->validate([
            'published_at' => ['required', 'date'],
        ]);

        $rateGroup->published_at = $validated['published_at'];
        $rateGroup->save();

        $rateGroup->load([
            'columns' => fn ($query) => $query->orderBy('order'),
            'rates',
        ]);

        return response()->json([
            'message' => 'Rate group scheduled.',
            'group' => new RateGroupResource($rateGroup),
            'columns' => ColumnResource::collection($rateGroup->columns),
            'rates' => RateResource::collection($rateGroup->rates),
        ], 200);
    }
}
