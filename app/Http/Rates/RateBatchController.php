<?php

namespace DDD\Http\Rates;

use Illuminate\Http\Request;
use DDD\App\Controllers\Controller;

// Models
use DDD\Domain\Organizations\Organization;
use DDD\Domain\Columns\Column;
use DDD\Domain\Rates\Rate;
use DDD\App\Traits\ResolvesRateGroup;

// Resources
use DDD\Http\Columns\Resources\ColumnResource;
use DDD\Http\Rates\Resources\RateResource;

// Requests
use DDD\Http\Rates\Requests\RateBatchRequest;

class RateBatchController extends Controller
{
    use ResolvesRateGroup;

    /**
     * Process a batch update of rate data for an organization.
     *
     * This will upsert rates, merge their payload data, upsert any non-unique ID
     * columns, and honor delete requests. A JSON response containing the refreshed
     * rate and column collections is returned for the requesting organization.
     *
     * @param Organization $organization
     * @param RateBatchRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handle(Organization $organization, RateBatchRequest $request)
    {
        // TODO: Validate $request->data includes "Unique ID"?

        $rateGroupId = $this->resolveRateGroupId($organization, $request->input('rate_group_id'));

        // Handle rates\ updates
        foreach ($request->rates as $r) {
            $uid = $r['uid'];
            unset($r['uid']); // Exclude uid
            
            $rate = Rate::withTrashed()->firstOrCreate(
                [
                    'uid' => $uid,
                    'organization_id' => $organization->id,
                ], 
                [
                    'organization_id' => $organization->id,
                    'user_id' => $request->user()->id,
                    'rate_group_id' => $rateGroupId,
                ]
            );
            
            if ($rate->trashed()) {
                $rate->restore();
            }

            if ($rate->rate_group_id !== $rateGroupId) {
                $rate->rate_group_id = $rateGroupId;
            }
            
            if (empty($r['data'])) {
                $rate->save();
                continue;
            }

            $rate['data'] = array_merge($rate['data'], $r['data']);

            $rate->save();
        }

        // Handle column updates
        foreach ($request->columns as $c) {
            if ($c['name'] === 'Unique ID') {
                continue;
            }

            $column = Column::updateOrCreate(
                [
                    'uid' => $c['uid'],
                    'organization_id' => $organization->id,
                ], 
                [
                    'uid' => $c['uid'],
                    'name' => $c['name'],
                    'organization_id' => $organization->id,
                    'user_id' => $request->user()->id,
                    'rate_group_id' => $rateGroupId,
                ]
            );

            if ($column->rate_group_id !== $rateGroupId) {
                $column->rate_group_id = $rateGroupId;
            }

            $column->save();
        }

        // Handle deletes
        foreach ($request->deletes as $delete) {
            if ($delete['model'] === 'rate') {
                $record = Rate::where('uid', $delete['uid'])->first();
            }

            if ($delete['model'] === 'column') {
                $record = Column::where('uid', $delete['uid'])->first();
            } 

            if ($record) {
                $record->delete();
            }
        }

        return response()->json([
            'message' => 'Rate batch handeled',
            'data' => [
                'rates' => RateResource::collection($organization->rates),
                'columns' => ColumnResource::collection($organization->columns),
            ]
        ], 200);
    }

}
