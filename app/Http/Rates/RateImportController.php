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
        // TODO: Validate $request->data includes "Unique ID"?

        // TODO: Change rows to 'rates' we don't use the term rows in the rate domain
        foreach ($request->rows as $row) {
            $uid = $row['Unique ID'];
            unset($row['Unique ID']);
            
            $rate = Rate::updateOrCreate(['uid' => $uid], [
                'organization_id' => $organization->id,
                'user_id' => $request->user()->id,
            ]);

            $rate->data = array_merge($rate->data, $row);

            $rate->save();
        }

        return response()->json([
            'message' => 'Rates imported',
            'data' => new RateResource($rate) // This shows the last rate updated
        ], 200);
    }
}
