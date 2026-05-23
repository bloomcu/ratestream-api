<?php

namespace DDD\Http\Rates;

use DDD\App\Controllers\Controller;

// Models
use DDD\App\Jobs\SyncPublishedRatesToWebsite;
use DDD\Domain\Organizations\Organization;
use DDD\Domain\Columns\Column;
use DDD\Domain\Rates\Rate;
use DDD\Domain\Rates\RateGroup;
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
                    'rate_group_id' => $rateGroupId,
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
                    'rate_group_id' => $rateGroupId,
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
            $record = null;

            $groupId = $this->resolveRateGroupId($organization, $delete['group_id'] ?? null);

            if ($delete['model'] === 'rate') {
                $record = Rate::where('uid', $delete['uid'])
                    ->where('organization_id', $organization->id)
                    ->where('rate_group_id', $groupId)
                    ->first();
            }

            if ($delete['model'] === 'column') {
                $record = Column::where('uid', $delete['uid'])
                    ->where('organization_id', $organization->id)
                    ->where('rate_group_id', $groupId)
                    ->first();
            } 

            if ($record) {
                $record->delete();
            }
        }

        $rateGroup = RateGroup::where('id', $rateGroupId)
            ->where('organization_id', $organization->id)
            ->first();

        if ($rateGroup && $this->shouldSyncPublishedRates($organization, $rateGroup)) {
            SyncPublishedRatesToWebsite::dispatch($organization->id, $rateGroup->id);
        }

        $rates = Rate::where('organization_id', $organization->id)
            ->where('rate_group_id', $rateGroupId)
            ->get();

        $columns = Column::where('organization_id', $organization->id)
            ->where('rate_group_id', $rateGroupId)
            ->orderBy('order')
            ->get();

        return response()->json([
            'message' => 'Rate batch handeled',
            'data' => [
                'rates' => RateResource::collection($rates),
                'columns' => ColumnResource::collection($columns),
            ]
        ], 200);
    }

    private function shouldSyncPublishedRates(Organization $organization, RateGroup $rateGroup): bool
    {
        return (int) $organization->default_rate_group_id === (int) $rateGroup->id
            && is_null($rateGroup->revision_of)
            && is_null($rateGroup->archived_at);
    }
}
