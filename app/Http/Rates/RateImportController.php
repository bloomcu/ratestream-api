<?php

namespace DDD\Http\Rates;

use Illuminate\Http\Request;
use DDD\App\Controllers\Controller;

// Models
use DDD\Domain\Organizations\Organization;
use DDD\Domain\Rates\Rate;

// Resources
use DDD\Http\Rates\Resources\RateResource;

class RateImportController extends Controller
{
    public function import(Organization $organization, Request $request)
    {
        foreach ($request->csv as $row) {
            $uid = $row['Unique ID'];
            unset($row['Unique ID']);

            // $group = isset($row['group']) ? $row['group'] : null;
            // unset($row['group']);

            $rate = Rate::updateOrCreate(['uid' => $uid], [
                'organization_id' => $organization->id,
                'user_id' => $request->user()->id,
                'rate_group_id' => $group,
                'columns' => $row
            ]);
        }

        return response()->json([
            'message' => 'Rates imported',
            'data' => new RateResource($rate)
        ], 200);

        // $rates = $organization->rates;
        // $groups = $rates->pluck('group')->unique()->flatten();
        // $request->csv
        // $rates = array_filter($request->csv, function ($row) {
        //     dd($row);
        //     return $row['group']['title'] != $group->title;
        // });

        // $mappings = [
        //     [
        //         'header' => 'year',
        //         'column' => 'year'
        //     ],
        //     [
        //         'header' => 'year_low',
        //         'column' => 'year_low'
        //     ],
        //     [
        //         'header' => 'year_high',
        //         'column' => 'year_high'
        //     ],
        //     [
        //         'header' => 'rate',
        //         'column' => 'rate'
        //     ],
        //     [
        //         'header' => 'term',
        //         'column' => 'term'
        //     ],
        // ];
    }
}
