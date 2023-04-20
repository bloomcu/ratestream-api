<?php

namespace DDD\Http\Rates;

use Illuminate\Http\Request;
use DDD\App\Controllers\Controller;

// Models
use DDD\Domain\Organizations\Organization;
use DDD\Domain\Rates\Rate;

// Resources
use DDD\Http\Rates\Resources\RateResource;

// Requests
use DDD\Http\Rates\Requests\RateImportRequest;

class RateImportController extends Controller
{
    public function import(Organization $organization, RateImportRequest $request)
    {
        // TODO: Validate each row has a Unique ID column
        
        foreach ($request->rows as $row) {
            $uid = $row['Unique ID'];
            unset($row['Unique ID']);
            
            $rate = Rate::updateOrCreate(['uid' => $uid], [
                'organization_id' => $organization->id,
                'user_id' => $request->user()->id,
            ]);

            $rate->columns = array_merge($rate->columns, $row);

            $rate->save();
        }

        return response()->json([
            'message' => 'Rates imported',
            'data' => new RateResource($rate)
        ], 200);
    }
}
