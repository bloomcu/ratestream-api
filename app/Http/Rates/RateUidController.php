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
use DDD\Http\Rates\Requests\RateUpdateUidRequest;

// Resources
use DDD\Http\Rates\Resources\RateResource;

class RateUidController extends Controller
{
    public function update(Organization $organization, Rate $rate, RateUpdateUidRequest $request)
    {
        $rate->update($request->validated());

        return new RateResource($rate);
    }
}
