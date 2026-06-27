<?php

namespace DDD\Http\Base\Organizations;

use Illuminate\Http\Request;
use DDD\App\Controllers\Controller;

// Models
use DDD\Domain\Base\Organizations\Organization;
use DDD\Domain\Base\Users\Enums\RoleEnum;

// Resources
use DDD\Domain\Base\Organizations\Resources\OrganizationResource;
use DDD\App\Traits\EnsuresDefaultRateGroup;

class OrganizationController extends Controller
{
    use EnsuresDefaultRateGroup;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $organizations = Organization::query()
            ->when(auth()->user()?->role !== RoleEnum::SuperAdmin, function ($query) {
                $query->where('id', auth()->user()->organization_id);
            })
            ->orderBy('title')
            ->get();

        return OrganizationResource::collection($organizations);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $organization = Organization::create($request->all());

        // Ensure a default rate group exists for the new organization
        $this->ensureDefaultRateGroup($organization, null);

        return new OrganizationResource($organization);
    }

    /**
     * Display the specified resource.
     */
    public function show(Organization $organization)
    {
        return new OrganizationResource($organization);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Organization $organization, Request $request)
    {
        $organization->update($request->only([
            'title',
            'rates_domain',
        ]));

        return new OrganizationResource($organization);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Organization $organization)
    {
        $organization->delete();

        return new OrganizationResource($organization);
    }
}
