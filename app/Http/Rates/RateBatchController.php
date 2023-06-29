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

        foreach ($request->rates as $rate) {
            $uid = $rate['Unique ID'];
            unset($rate['Unique ID']); // Excludes uid from columns
            
            $record = Rate::updateOrCreate(
                [
                    'uid' => $uid,
                    'organization_id' => $organization->id,
                ], 
                [
                    'organization_id' => $organization->id,
                    'user_id' => $request->user()->id,
                ]
            );

            $record->data = array_merge($record->data, $rate);

            $record->save();
        }

        foreach ($request->columns as $column) {
            if ($column === 'Unique ID') {
                continue;
            }

            $column = Column::create([
                'organization_id' => $organization->id,
                'user_id' => $request->user()->id,
                'name' => $column,
            ]);

            $column->save();
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
