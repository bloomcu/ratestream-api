<?php

namespace Tests\Unit\App\Services\RateGroups;

use DDD\App\Jobs\SyncPublishedRatesToWebsite;
use DDD\App\Services\RateGroups\RateGroupPublisher;
use DDD\Domain\Base\Users\User;
use DDD\Domain\Organizations\Organization;
use DDD\Domain\Rates\RateGroup;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RateGroupPublisherTest extends TestCase
{
    /** @test */
    public function it_dispatches_the_website_sync_job_after_publishing_a_revision()
    {
        Queue::fake();

        [$organization, $revision] = $this->revision();

        $publishedGroup = app(RateGroupPublisher::class)->publish($organization, $revision);

        Queue::assertPushed(SyncPublishedRatesToWebsite::class, function ($job) use ($organization, $publishedGroup) {
            return $job->organizationId === $organization->id
                && $job->publishedRateGroupId === $publishedGroup->id;
        });
        Queue::assertPushed(SyncPublishedRatesToWebsite::class, 1);
    }

    private function revision(): array
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

        $originalGroup = RateGroup::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'title' => 'Published',
            'published_at' => now()->subDay(),
        ]);

        $revision = RateGroup::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'title' => 'Revision',
            'revision_of' => $originalGroup->id,
        ]);

        $organization->default_rate_group_id = $originalGroup->id;
        $organization->save();

        return [$organization, $revision];
    }
}
