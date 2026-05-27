<?php

namespace DDD\Http\Columns;

use Illuminate\Http\Request;
use DDD\App\Controllers\Controller;

// Models
use DDD\Domain\Organizations\Organization;
use DDD\Domain\Columns\Column;

// Requests
use DDD\Http\Columns\Requests\ColumnOrderUpdateRequest;

// Resources
use DDD\Http\Columns\Resources\ColumnResource;

class ColumnOrderController extends Controller
{
    public function update(Organization $organization, Column $column, ColumnOrderUpdateRequest $request)
    {
        if ($column->organization_id !== $organization->id) {
            abort(404);
        }
        $column->reorder($request->order);

        return ColumnResource::collection($organization->columns);
    }
}
