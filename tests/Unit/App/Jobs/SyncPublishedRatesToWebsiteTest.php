<?php

namespace Tests\Unit\App\Jobs;

use DDD\App\Jobs\SyncPublishedRatesToWebsite;
use DDD\Domain\Base\Users\User;
use DDD\Domain\Organizations\Organization;
use DDD\Domain\Rates\RateGroup;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncPublishedRatesToWebsiteTest extends TestCase
{
    /** @test */
    public function it_posts_to_the_organizations_rates_domain()
    {
        Http::fake([
            'https://example.com/wp-json/ratestream/v1/sync' => Http::response([], 200),
        ]);

        [$organization, $publishedGroup] = $this->publishedGroup('example.com');

        (new SyncPublishedRatesToWebsite($organization->id, $publishedGroup->id))->handle();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://example.com/wp-json/ratestream/v1/sync'
                && $request->method() === 'POST'
                && $request->hasHeader('X-RateStream-Secret', 'test-secret');
        });
    }

    /** @test */
    public function it_preserves_an_existing_scheme_and_port()
    {
        Http::fake([
            'http://localhost:8080/wp-json/ratestream/v1/sync' => Http::response([], 200),
        ]);

        [$organization, $publishedGroup] = $this->publishedGroup('http://localhost:8080/');

        (new SyncPublishedRatesToWebsite($organization->id, $publishedGroup->id))->handle();

        Http::assertSent(function ($request) {
            return $request->url() === 'http://localhost:8080/wp-json/ratestream/v1/sync'
                && $request->hasHeader('X-RateStream-Secret', 'test-secret');
        });
    }

    /** @test */
    public function it_skips_the_request_when_the_organization_has_no_rates_domain()
    {
        Http::fake();

        [$organization, $publishedGroup] = $this->publishedGroup(null);

        (new SyncPublishedRatesToWebsite($organization->id, $publishedGroup->id))->handle();

        Http::assertNothingSent();
    }

    private function publishedGroup(?string $ratesDomain): array
    {
        $organization = Organization::create([
            'title' => 'Acme Credit Union',
            'rates_domain' => $ratesDomain,
        ]);

        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin-' . uniqid() . '@example.com',
            'role' => 'admin',
            'organization_id' => $organization->id,
            'password' => bcrypt('password'),
        ]);

        $publishedGroup = RateGroup::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'title' => 'Published',
            'published_at' => now(),
        ]);

        return [$organization, $publishedGroup];
    }
}
