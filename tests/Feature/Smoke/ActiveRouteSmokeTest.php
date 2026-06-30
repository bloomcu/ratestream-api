<?php

namespace Tests\Feature\Smoke;

use PHPUnit\Framework\Attributes\Test;
use DDD\Domain\Base\Files\File;
use DDD\Domain\Base\Invitations\Invitation;
use DDD\Domain\Base\Subscriptions\Plans\Plan;
use DDD\Domain\Base\Users\User;
use DDD\Domain\Columns\Column;
use DDD\Domain\Organizations\Organization;
use DDD\Domain\Rates\Rate;
use DDD\Domain\Rates\RateGroup;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ActiveRouteSmokeTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->ensureFreePlan();
    }

    #[Test]
    public function active_product_route_signatures_are_registered()
    {
        $registeredRoutes = collect(Route::getRoutes())
            ->mapWithKeys(function ($route) {
                $methods = collect($route->methods())
                    ->reject(fn ($method) => $method === 'HEAD')
                    ->sort()
                    ->implode('|');

                return [$methods . ' ' . $route->uri() => true];
            });

        foreach ($this->activeProductRouteSignatures() as $routeSignature) {
            $this->assertTrue(
                $registeredRoutes->has($routeSignature),
                "Expected route [{$routeSignature}] to be registered."
            );
        }
    }

    #[Test]
    public function representative_active_product_routes_resolve_without_server_errors()
    {
        Queue::fake();
        Storage::fake();

        [$organization, $admin, $group] = $this->organizationWithAdminAndPublishedGroup();
        $column = $this->column($organization, $admin, $group);
        $rate = $this->rate($organization, $admin, $group);
        $file = $this->csvFile($organization, $admin);
        $invitation = $this->invitation($organization, $admin);

        $responses = [
            $this->postJson('/api/auth/login', [
                'email' => $admin->email,
                'password' => 'password',
            ]),
            $this->getJson("/api/{$organization->slug}/rates"),
            $this->getJson("/api/{$organization->slug}/rates/export"),
            $this->getJson("/api/{$organization->slug}/invitations/{$invitation->uuid}"),
            $this->actingAs($admin, 'sanctum')->getJson('/api/auth/me'),
            $this->actingAs($admin, 'sanctum')->getJson('/api/organizations'),
            $this->actingAs($admin, 'sanctum')->getJson("/api/organizations/{$organization->slug}"),
            $this->actingAs($admin, 'sanctum')->getJson("/api/{$organization->slug}/users"),
            $this->actingAs($admin, 'sanctum')->getJson("/api/{$organization->slug}/invitations"),
            $this->actingAs($admin, 'sanctum')->getJson("/api/{$organization->slug}/rate-groups"),
            $this->actingAs($admin, 'sanctum')->getJson("/api/{$organization->slug}/rate-groups/{$group->id}/revisions"),
            $this->actingAs($admin, 'sanctum')->getJson("/api/{$organization->slug}/rates/sync-key"),
            $this->actingAs($admin, 'sanctum')->getJson("/api/{$organization->slug}/csv/{$file->id}"),
            $this->actingAs($admin, 'sanctum')->postJson("/api/{$organization->slug}/columns", [
                'name' => 'Term',
                'rate_group_id' => $group->id,
            ]),
            $this->actingAs($admin, 'sanctum')->putJson("/api/{$organization->slug}/columns/{$column->id}/order", [
                'order' => 2,
            ]),
            $this->actingAs($admin, 'sanctum')->putJson("/api/{$organization->slug}/rates/uid/update/{$rate->uid}", [
                'uid' => 'updated-auto-loan',
            ]),
            $this->actingAs($admin, 'sanctum')->postJson("/api/{$organization->slug}/rates/batch", [
                'rate_group_id' => $group->id,
                'rates' => [],
                'columns' => [],
                'deletes' => [],
            ]),
        ];

        foreach ($responses as $response) {
            $this->assertLessThan(
                500,
                $response->getStatusCode(),
                'Expected representative active route to resolve without a server error.'
            );
        }
    }

    private function activeProductRouteSignatures(): array
    {
        return [
            'POST api/auth/login',
            'POST api/auth/logout',
            'GET api/auth/me',
            'POST api/auth/password/forgot',
            'POST api/auth/password/reset',
            'POST api/auth/register',
            'POST api/auth/register/invitation/{invitation}',
            'GET api/{organization}/invitations/{invitation}',
            'GET api/{organization}/invitations',
            'POST api/{organization}/invitations',
            'DELETE api/{organization}/invitations/{invitation}',
            'GET api/organizations',
            'POST api/organizations',
            'GET api/organizations/{organization}',
            'PUT api/organizations/{organization}',
            'DELETE api/organizations/{organization}',
            'GET api/{organization}/users',
            'PATCH api/{organization}/users/{user}/role',
            'DELETE api/{organization}/users/{user}',
            'GET api/{organization}/rates',
            'GET api/{organization}/rates/export',
            'POST api/{organization}/rates/batch',
            'GET api/{organization}/rates/sync-key',
            'POST api/{organization}/rates/sync-key/rotate',
            'PUT api/{organization}/rates/uid/update/{rate}',
            'POST api/{organization}/columns',
            'PUT api/{organization}/columns/{column}/order',
            'GET api/{organization}/csv/{file}',
            'GET api/{organization}/rate-groups',
            'POST api/{organization}/rate-groups/{rateGroup}/clone',
            'POST api/{organization}/rate-groups/{rateGroup}/publish',
            'GET api/{organization}/rate-groups/{rateGroup}/revisions',
            'POST api/{organization}/rate-groups/{rateGroup}/schedule',
        ];
    }

    private function organizationWithAdminAndPublishedGroup(): array
    {
        $uid = uniqid();

        $organization = Organization::create([
            'title' => 'Acme Credit Union ' . $uid,
            'rates_domain' => 'https://' . $uid . '.example.com',
        ]);

        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin-' . uniqid() . '@example.com',
            'role' => 'admin',
            'organization_id' => $organization->id,
            'password' => bcrypt('password'),
        ]);

        $group = RateGroup::create([
            'organization_id' => $organization->id,
            'user_id' => $admin->id,
            'title' => 'Published',
            'published_at' => now(),
            'position' => 1,
        ]);

        $organization->update([
            'default_rate_group_id' => $group->id,
        ]);

        return [$organization->fresh(), $admin, $group];
    }

    private function column(Organization $organization, User $user, RateGroup $group): Column
    {
        return Column::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'rate_group_id' => $group->id,
            'uid' => 'apr',
            'name' => 'APR',
            'order' => 1,
        ]);
    }

    private function rate(Organization $organization, User $user, RateGroup $group): Rate
    {
        return Rate::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'rate_group_id' => $group->id,
            'uid' => 'auto-loan',
            'data' => [
                'APR' => '5.99%',
            ],
        ]);
    }

    private function csvFile(Organization $organization, User $user): File
    {
        $path = $organization->slug . '/rates.csv';

        Storage::put($path, "Unique ID,apr\n,APR\nrate-1,5.99%\n");

        return File::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'name' => 'rates',
            'filename' => 'rates.csv',
            'path' => $path,
            'extension' => 'csv',
            'mime' => 'text/csv',
            'disk' => config('filesystems.default'),
        ]);
    }

    private function invitation(Organization $organization, User $user): Invitation
    {
        return Invitation::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'email' => 'invitee-' . uniqid() . '@example.com',
            'role' => 'editor',
        ]);
    }

    private function ensureFreePlan(): void
    {
        Plan::firstOrCreate(
            ['buyable' => false],
            [
                'title' => 'Free',
                'slug' => 'free',
                'price' => 0,
                'interval' => null,
                'limits' => [],
            ]
        );
    }
}
