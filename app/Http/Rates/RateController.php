<?php

namespace DDD\Http\Rates;

use Illuminate\Http\Request;
use DDD\App\Controllers\Controller;

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
        // Render rates nested into groups from api
        // -----
        $rates = $organization->rates;
        $groups = $rates->pluck('group')->unique()->flatten();
        foreach ($groups as $group) {
            $group->rates = array_filter($rates->toArray(), function ($rate) use ($group) {
                return $rate['group']['title'] === $group->title;
            });
        }
        return $groups;

        // Render rates using filter in store
        // -----
        // $rates = $organization->rates;
        // $groups = $rates->pluck('group')->unique()->flatten();
        // return [
        //     'rates' => RateResource::collection($rates),
        //     'groups' => $groups
        // ];

        // return RateResource::collection($rates);
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

        // return new RateResource($rate);
        return $rate;
    }

    public function destroy(Organization $organization, Rate $rate)
    {
        $rate->delete();

        // return new RateResource($rate);
        return $rate;
    }
}
