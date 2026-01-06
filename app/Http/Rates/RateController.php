<?php

namespace DDD\Http\Rates;

use Illuminate\Http\Request;
use DDD\App\Controllers\Controller;

// Vendors
use Spatie\QueryBuilder\QueryBuilder;

// Models
use DDD\Domain\Organizations\Organization;
use DDD\Domain\Rates\Rate;
use DDD\Domain\Columns\Column;
use DDD\App\Traits\ResolvesRateGroup;

// Requests
use DDD\Http\Rates\Requests\RateStoreRequest;
use DDD\Http\Rates\Requests\RateUpdateRequest;

// Resources
use DDD\Http\Rates\Resources\RateResource;
use DDD\Http\Columns\Resources\ColumnResource;
use Illuminate\Support\Facades\Log;

class RateController extends Controller
{
    use ResolvesRateGroup;

    public function index(Organization $organization, Request $request)
    {
        Log::info('RateController index invoked', ['request_data' => $request->all()]); 
        $rateGroupId = $this->resolveRateGroupId($organization, $request->input('rate_group_id'));
        Log::info("Resolved Rate Group ID: " . $rateGroupId);
        $rates = QueryBuilder::for(Rate::class)
            ->where('organization_id', $organization->id)
            ->where('rate_group_id', $rateGroupId)
            ->allowedFilters(['uid', 'data->rate'])
            ->get();

        $columns = Column::where('organization_id', $organization->id)
            ->where('rate_group_id', $rateGroupId)
            ->orderBy('order')
            ->get();

        return [
            'columns' => ColumnResource::collection($columns),
            'rates' => RateResource::collection($rates),
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
