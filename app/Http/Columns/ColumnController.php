<?php

namespace DDD\Http\Columns;

use Illuminate\Http\Request;
use DDD\App\Controllers\Controller;

// Models
use DDD\Domain\Organizations\Organization;
use DDD\Domain\Columns\Column;
use DDD\App\Traits\ResolvesRateGroup;

// Requests
use DDD\Http\Columns\Requests\ColumnStoreRequest;
use DDD\Http\Columns\Requests\ColumnUpdateRequest;

// Resources
use DDD\Http\Columns\Resources\ColumnResource;

class ColumnController extends Controller
{
    use ResolvesRateGroup;

    public function index(Organization $organization)
    {
        $columns = $organization->columns()->sort('order')->get();

        return ColumnResource::collection($columns);
    }

    public function store(Organization $organization, ColumnStoreRequest $request)
    {
        $rateGroupId = $this->resolveRateGroupId($organization, $request->input('rate_group_id'));

        $payload = $request->validated();
        $payload['rate_group_id'] = $rateGroupId;

        $column = $organization->columns()->create($payload);

        return new ColumnResource($column);
    }

    public function show(Organization $organization, Column $column)
    {
        return new ColumnResource($column);
    }

    public function update(Organization $organization, Column $column, ColumnUpdateRequest $request)
    {
        $column->update($request->validated());

        return new ColumnResource($column);
    }

    public function destroy(Organization $organization, Column $column)
    {
        $column->delete();

        return new ColumnResource($column);
    }

}
