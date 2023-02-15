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
        $rates = $organization->rates;

        return RateResource::collection($rates);
    }

    public function store(Organization $organization, RateStoreRequest $request)
    {
        $rate = $organization->rates()->create($request->validated());

        return new RateResource($rate);
    }

    public function show(Organization $organization, Rate $rate)
    {
        return new RateResource($rate);
    }

    public function update(Organization $organization, Rate $rate, RateUpdateRequest $request)
    {
        $rate->update($request->validated());

        return new RateResource($rate);
    }

    public function destroy(Organization $organization, Rate $rate)
    {
        $rate->delete();

        return new RateResource($rate);
    }
}
