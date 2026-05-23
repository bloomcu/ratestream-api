<?php

namespace Tests\Feature\Rates;

use DDD\App\Jobs\SyncPublishedRatesToWebsite;
use DDD\Domain\Base\Users\User;
use DDD\Domain\Organizations\Organization;
use DDD\Domain\Rates\RateGroup;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RateBatchControllerTest extends TestCase
{
    /** @test */
    public function it_dispatches_the_website_sync_job_after_batch_updating_the_default_published_group()
    {
        Queue::fake();

        [$organization, $user, $publishedGroup] = $this->organizationWithPublishedGroup();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/{$organization->slug}/rates/batch", [
                'rates' => [
                    [
                        'uid' => 'auto-loan',
                        'data' => [
                            'Product' => 'Auto Loan',
                            'APR' => '5.99%',
                        ],
                    ],
                ],
                'columns' => [],
                'deletes' => [],
            ]);

        $response->assertOk();

        Queue::assertPushed(SyncPublishedRatesToWebsite::class, function ($job) use ($organization, $publishedGroup) {
            return $job->organizationId === $organization->id
                && $job->publishedRateGroupId === $publishedGroup->id;
        });
        Queue::assertPushed(SyncPublishedRatesToWebsite::class, 1);
    }

    /** @test */
    public function it_does_not_dispatch_the_website_sync_job_after_batch_updating_a_draft_revision()
    {
        Queue::fake();

        [$organization, $user, $publishedGroup] = $this->organizationWithPublishedGroup();

        $revision = RateGroup::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'title' => 'Draft Revision',
            'revision_of' => $publishedGroup->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/{$organization->slug}/rates/batch", [
                'rate_group_id' => $revision->id,
                'rates' => [
                    [
                        'uid' => 'auto-loan',
                        'data' => [
                            'Product' => 'Auto Loan',
                            'APR' => '5.99%',
                        ],
                    ],
                ],
                'columns' => [],
                'deletes' => [],
            ]);

        $response->assertOk();

        Queue::assertNotPushed(SyncPublishedRatesToWebsite::class);
    }

    private function organizationWithPublishedGroup(): array
    {
        $organization = Organization::create([
            'title' => 'Acme Credit Union',
            'rates_domain' => 'https://example.com',
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

        $organization->default_rate_group_id = $publishedGroup->id;
        $organization->save();

        return [$organization->fresh(), $user, $publishedGroup];
    }
}
