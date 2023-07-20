<?php

namespace DDD\Http\Rates;

use Illuminate\Http\Request;
use DDD\App\Controllers\Controller;

// Vendors
use Spatie\QueryBuilder\QueryBuilder;

// Models
use DDD\Domain\Organizations\Organization;
use DDD\Domain\Rates\Rate;

// Requests
use DDD\Http\Rates\Requests\RateStoreRequest;
use DDD\Http\Rates\Requests\RateUpdateRequest;

// Resources
use DDD\Http\Rates\Resources\RateResource;
use DDD\Http\Columns\Resources\ColumnResource;

class RateController extends Controller
{
    public function index(Organization $organization)
    {
        $rates = QueryBuilder::for(Rate::class)
            ->where('organization_id', $organization->id)
            ->allowedFilters(['uid', 'data->rate'])
            ->get();

        $columns = $organization->columns()->orderBy('order')->get();

        return [
            'rates' => RateResource::collection($rates),
            'columns' => ColumnResource::collection($columns),
        ];

        // Pluck rate groups
        // $rates = $organization->rates;
        // $groups = $rates->pluck('group')->filter()->unique()->flatten();
        // return [
        //     'rates' => RateResource::collection($rates),
        //     'groups' => $groups
        // ];
    }

    // public function store(Organization $organization, RateStoreRequest $request)
    // {
    //     $rate = $organization->rates()->create($request->validated());

    //     return new RateResource($rate);
    // }

    // public function show(Organization $organization, Rate $rate)
    // {
    //     return new RateResource($rate);
    // }

    // public function update(Organization $organization, Rate $rate, RateUpdateRequest $request)
    // {
    //     $rate->update($request->validated());

    //     return new RateResource($rate);
    // }

    // public function destroy(Organization $organization, Rate $rate)
    // {
    //     $rate->delete();

    //     return new RateResource($rate);
    // }
}
