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
use DDD\Http\Rates\Requests\RateImportRequest;

class RateImportController extends Controller
{
    public function import(Organization $organization, RateImportRequest $request)
    {
        // TODO: Validate $request->data includes "Unique ID"?

        foreach ($request->rows as $row) {
            $uid = $row['Unique ID'];
            unset($row['Unique ID']); // Excludes uid from columns
            
            $rate = Rate::updateOrCreate(
                [
                    'uid' => $uid,
                    'organization_id' => $organization->id,
                ], 
                [
                    'organization_id' => $organization->id,
                    'user_id' => $request->user()->id,
                ]
            );

            $rate->data = array_merge($rate->data, $row);

            // Trim each key and value
            // $keys = array_map('trim', array_keys($row));
            // $values = array_map('trim', $row);
            // $trimmedData = array_combine($keys, $values);
            // $rate->data = array_merge($rate->data, $trimmedData);
            // dd([
            //     'original' => $row,
            //     'trimmed' => $trimmedData
            // ]);

            $rate->save();
        }

        foreach ($request->columns as $column) {
            if ($column === 'Unique ID') {
                continue;
            }

            $column = Column::firstOrNew(
                [
                    'organization_id' => $organization->id,
                    'name' => $column,
                ], 
                [
                    'organization_id' => $organization->id,
                    'user_id' => $request->user()->id,
                    'name' => $column,
                ]
            );

            $column->save();
        }

        return response()->json([
            'message' => 'Rates imported',
            'data' => [
                'columns' => ColumnResource::collection($organization->columns),
                'rates' => RateResource::collection($organization->rates),
            ]
        ], 200);
    }
}
