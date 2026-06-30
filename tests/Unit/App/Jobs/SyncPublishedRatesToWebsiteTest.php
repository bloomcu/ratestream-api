<?php

namespace Tests\Unit\App\Jobs;

use PHPUnit\Framework\Attributes\Test;
use DDD\App\Jobs\SyncPublishedRatesToWebsite;
use DDD\Domain\Base\Users\User;
use DDD\Domain\Organizations\Organization;
use DDD\Domain\Rates\RateGroup;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SyncPublishedRatesToWebsiteTest extends TestCase
{
    #[Test]
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
                && $request->hasHeader('X-RateStream-Secret', 'org-sync-key');
        });
    }

    #[Test]
    public function it_preserves_an_existing_scheme_and_port()
    {
        Http::fake([
            'http://localhost:8080/wp-json/ratestream/v1/sync' => Http::response([], 200),
        ]);

        [$organization, $publishedGroup] = $this->publishedGroup('http://localhost:8080/');

        (new SyncPublishedRatesToWebsite($organization->id, $publishedGroup->id))->handle();

        Http::assertSent(function ($request) {
            return $request->url() === 'http://localhost:8080/wp-json/ratestream/v1/sync'
                && $request->hasHeader('X-RateStream-Secret', 'org-sync-key');
        });
    }

    #[Test]
    public function it_skips_the_request_when_the_organization_has_no_rates_domain()
    {
        Http::fake();

        [$organization, $publishedGroup] = $this->publishedGroup(null);

        (new SyncPublishedRatesToWebsite($organization->id, $publishedGroup->id))->handle();

        Http::assertNothingSent();
    }

    #[Test]
    public function it_skips_the_request_when_the_organization_has_no_rates_sync_key()
    {
        Http::fake();

        [$organization, $publishedGroup] = $this->publishedGroup('example.com', null);

        (new SyncPublishedRatesToWebsite($organization->id, $publishedGroup->id))->handle();

        Http::assertNothingSent();
    }

    #[Test]
    public function it_throws_for_unsuccessful_webhook_responses_without_logging_the_sync_secret()
    {
        Log::spy();

        Http::fake([
            'https://example.com/wp-json/ratestream/v1/sync' => Http::response(['error' => 'server'], 500),
        ]);

        [$organization, $publishedGroup] = $this->publishedGroup('example.com');

        try {
            (new SyncPublishedRatesToWebsite($organization->id, $publishedGroup->id))->handle();
            $this->fail('Expected webhook failure to throw.');
        } catch (\Throwable $exception) {
            $this->assertSame(500, $exception->response->status());
        }

        // Failure logging should include response details without leaking the sync secret.
        Log::shouldHaveReceived('warning')->withArgs(function ($message, $context) {
            return $message === 'Published rates webhook returned an unsuccessful response.'
                && ($context['status'] ?? null) === 500
                && ! isset($context['X-RateStream-Secret'])
                && ! str_contains(json_encode($context), 'org-sync-key');
        })->once();
    }

    private function publishedGroup(?string $ratesDomain, ?string $ratesSyncKey = 'org-sync-key'): array
    {
        $organization = Organization::create([
            'title' => 'Acme Credit Union',
            'rates_domain' => $ratesDomain,
            'rates_sync_key' => $ratesSyncKey,
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
