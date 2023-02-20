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

class RateController extends Controller
{
    public function index(Organization $organization)
    {
        $rates = QueryBuilder::for(Rate::class)
            ->where('organization_id', $organization->id)
            ->allowedFilters(['uid', 'columns->rate'])
            ->get();

        $columns = $rates->map(function ($rate) {
            return collect($rate->columns)->keys();
        })->unique()->flatten();

        return [
            'rates' => RateResource::collection($rates),
            'columns' => $columns
        ];

        // Pluck rate groups
        // $rates = $organization->rates;
        // $groups = $rates->pluck('group')->filter()->unique()->flatten();
        // return [
        //     'rates' => RateResource::collection($rates),
        //     'groups' => $groups
        // ];
    }

    public function store(Organization $organization, RateStoreRequest $request)
    {
        $rate = $organization->rates()->create($request->validated());

        // return new RateResource($rate);
        return $rate;
    }

    public function show(Organization $organization, Rate $rate)
    {
        // return new RateResource($rate);
        return $rate;
    }

    public function update(Organization $organization, Rate $rate, RateUpdateRequest $request)
    {
        $rate->update($request->validated());

        return new RateResource($rate);
        // return $rate;
    }

    public function destroy(Organization $organization, Rate $rate)
    {
        $rate->delete();

        // return new RateResource($rate);
        return $rate;
    }
}
