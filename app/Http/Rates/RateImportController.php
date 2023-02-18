<?php

namespace DDD\Http\Rates;

use Illuminate\Http\Request;
use DDD\App\Controllers\Controller;

// Models
use DDD\Domain\Organizations\Organization;
use DDD\Domain\Rates\Rate;

class RateImportController extends Controller
{
    public function import(Organization $organization, Request $request)
    {
        foreach ($request->csv as $row) {
            $uid = $row['uid'];
            $group = $row['group'];
            unset($row['uid']);
            unset($row['group']);

            $rate = Rate::updateOrCreate(['uid' => $uid], [
                'organization_id' => $organization->id,
                'user_id' => $request->user()->id,
                'rate_group_id' => $group,
                'columns' => $row
            ]);
        }

        return response()->json([
            'message' => 'Rates imported'
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

        // $organization->rates()->upsert(
        //     [], // values to be upserted
        //     ['name'], // unique identifier
        //     ['group', 'columns'] // columns to be updated
        // );
    }
}
