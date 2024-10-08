<?php

namespace DDD\Http\Rates;

use Illuminate\Http\Request;
use DDD\App\Controllers\Controller;

// Models
use DDD\Domain\Organizations\Organization;
use DDD\Domain\Columns\Column;
use DDD\Domain\Rates\Rate;

// Resources
use DDD\Http\Columns\Resources\ColumnResource;
use DDD\Http\Rates\Resources\RateResource;

// Requests
use DDD\Http\Rates\Requests\RateBatchRequest;

class RateBatchController extends Controller
{
    public function handle(Organization $organization, RateBatchRequest $request)
    {
        // TODO: Validate $request->data includes "Unique ID"?

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
                ]
            );
            
            if ($rate->trashed()) {
                $rate->restore();
            }
            
            if (empty($r['data'])) {
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
                ]
            );

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
