<?php

namespace DDD\Http\Rates;

use DDD\App\Controllers\Controller;
use DDD\Domain\Organizations\Organization;
use Illuminate\Support\Str;

class RateSyncKeyController extends Controller
{
    public function show(Organization $organization)
    {
        $created = false;

        if (! $organization->rates_sync_key) {
            $organization->rates_sync_key = $this->newKey();
            $organization->save();
            $created = true;
        }

        return response()->json([
            'data' => [
                'key' => $organization->rates_sync_key,
                'created' => $created,
            ],
        ]);
    }

    public function rotate(Organization $organization)
    {
        $organization->rates_sync_key = $this->newKey();
        $organization->save();

        return response()->json([
            'message' => 'Rates sync key rotated.',
            'data' => [
                'key' => $organization->rates_sync_key,
            ],
        ]);
    }

    private function newKey(): string
    {
        return Str::random(64);
    }
}
