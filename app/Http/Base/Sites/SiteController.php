<?php

namespace DDD\Http\Base\Sites;

use Illuminate\Http\Request;
use DDD\App\Controllers\Controller;

// Models
use DDD\Domain\Base\Organizations\Organization;
use DDD\Domain\Base\Sites\Site;

// Services
use DDD\App\Helpers\UrlHelpers;

// Requests
use DDD\Domain\Base\Sites\Requests\SiteStoreRequest;
use DDD\Domain\Base\Sites\Requests\SiteUpdateRequest;

// Resources
use DDD\Domain\Base\Sites\Resources\SiteResource;

class SiteController extends Controller
{
    public function index(Organization $organization)
    {
        return SiteResource::collection($organization->sites);
    }

    public function store(Organization $organization, SiteStoreRequest $request)
    {
        $site = $organization->sites()->create([
            'title' => $request->title,
            'url' => $request->url,
            'domain' => $request->domain, // TODO: Do we need this? If so, make into trait and cast so all url parts are updated
            'scheme' => UrlHelpers::getScheme($request->domain), // TODO: Do we need this?
            'launch_info' => $request->launch_info,
        ]);

        return new SiteResource($site);
    }

    public function show(Organization $organization, Site $site)
    {
        return new SiteResource($site);
    }

    public function update(Organization $organization, Site $site, SiteUpdateRequest $request)
    {
        $site->update($request->validated());

        return new SiteResource($site);
    }

    public function destroy(Organization $organization, Site $site)
    {
        $site->delete();

        return new SiteResource($site);
    }
}
