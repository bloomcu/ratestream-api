<?php

namespace Tests\Feature\Rates;

use PHPUnit\Framework\Attributes\Test;
use DDD\App\Jobs\SyncPublishedRatesToWebsite;
use DDD\Domain\Base\Users\User;
use DDD\Domain\Organizations\Organization;
use DDD\Domain\Rates\Rate;
use DDD\Domain\Rates\RateGroup;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RateBatchControllerTest extends TestCase
{
    #[Test]
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

    #[Test]
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

    #[Test]
    public function it_saves_batch_updates_to_the_requested_revision_group_without_touching_the_published_group()
    {
        Queue::fake();

        [$organization, $user, $publishedGroup, $revision] = $this->organizationWithPublishedGroupAndRevisionRate();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/{$organization->slug}/rates/batch", [
                'rate_group_id' => $revision->id,
                'rates' => [
                    [
                        'uid' => 'auto-loan',
                        'data' => [
                            'Product' => 'Revision Auto Loan',
                            'APR' => '5.99%',
                        ],
                    ],
                ],
                'columns' => [],
                'deletes' => [],
            ]);

        $response->assertOk();

        $publishedRate = Rate::where('organization_id', $organization->id)
            ->where('rate_group_id', $publishedGroup->id)
            ->where('uid', 'auto-loan')
            ->first();

        $revisionRate = Rate::where('organization_id', $organization->id)
            ->where('rate_group_id', $revision->id)
            ->where('uid', 'auto-loan')
            ->first();

        $this->assertSame('Published Auto Loan', $publishedRate->data['Product']);
        $this->assertSame('7.99%', $publishedRate->data['APR']);
        $this->assertSame('Revision Auto Loan', $revisionRate->data['Product']);
        $this->assertSame('5.99%', $revisionRate->data['APR']);
        Queue::assertNotPushed(SyncPublishedRatesToWebsite::class);
    }

    #[Test]
    public function it_returns_only_the_requested_revision_group_after_batch_updating_a_revision()
    {
        Queue::fake();

        [$organization, $user, , $revision] = $this->organizationWithPublishedGroupAndRevisionRate();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/{$organization->slug}/rates/batch", [
                'rate_group_id' => $revision->id,
                'rates' => [
                    [
                        'uid' => 'auto-loan',
                        'data' => [
                            'Product' => 'Revision Auto Loan',
                            'APR' => '5.99%',
                        ],
                    ],
                ],
                'columns' => [],
                'deletes' => [],
            ]);

        $response->assertOk();

        $rates = $response->json('data.rates');

        $this->assertCount(1, $rates);
        $this->assertSame('auto-loan', $rates[0]['uid']);
        $this->assertSame('Revision Auto Loan', $rates[0]['data']['Product']);
        $this->assertSame('5.99%', $rates[0]['data']['APR']);
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

    private function organizationWithPublishedGroupAndRevisionRate(): array
    {
        [$organization, $user, $publishedGroup] = $this->organizationWithPublishedGroup();

        $revision = RateGroup::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'title' => 'Draft Revision',
            'revision_of' => $publishedGroup->id,
        ]);

        Rate::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'uid' => 'auto-loan',
            'rate_group_id' => $publishedGroup->id,
            'data' => [
                'Product' => 'Published Auto Loan',
                'APR' => '7.99%',
            ],
        ]);

        Rate::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'uid' => 'auto-loan',
            'rate_group_id' => $revision->id,
            'data' => [
                'Product' => 'Revision Auto Loan',
                'APR' => '7.99%',
            ],
        ]);

        return [$organization, $user, $publishedGroup, $revision];
    }
}
