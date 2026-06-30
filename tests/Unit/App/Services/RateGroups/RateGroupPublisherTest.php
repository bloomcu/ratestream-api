<?php

namespace Tests\Unit\App\Services\RateGroups;

use PHPUnit\Framework\Attributes\Test;
use DDD\App\Jobs\SyncPublishedRatesToWebsite;
use DDD\App\Services\RateGroups\RateGroupPublisher;
use DDD\Domain\Base\Users\User;
use DDD\Domain\Organizations\Organization;
use DDD\Domain\Rates\Rate;
use DDD\Domain\Rates\RateGroup;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RateGroupPublisherTest extends TestCase
{
    #[Test]
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

    #[Test]
    public function it_promotes_the_revision_rate_state_when_publishing()
    {
        Queue::fake();

        [$organization, $revision, $originalGroup, $user] = $this->revisionWithRateState();

        $revisionRateBeforePublish = Rate::where('organization_id', $organization->id)
            ->where('rate_group_id', $revision->id)
            ->where('uid', 'auto-loan')
            ->first();

        $this->assertSame('Revision Auto Loan', $revisionRateBeforePublish->data['Product']);
        $this->assertSame('5.99%', $revisionRateBeforePublish->data['APR']);

        $publishedGroup = app(RateGroupPublisher::class)->publish($organization, $revision);

        $publishedRate = Rate::where('organization_id', $organization->id)
            ->where('rate_group_id', $publishedGroup->id)
            ->where('uid', 'auto-loan')
            ->first();

        $originalRate = Rate::where('organization_id', $organization->id)
            ->where('rate_group_id', $originalGroup->id)
            ->where('uid', 'auto-loan')
            ->first();

        $this->assertSame($revision->id, $publishedGroup->id);
        $this->assertSame($revision->id, $organization->fresh()->default_rate_group_id);
        $this->assertSame($revision->id, $originalGroup->fresh()->superseded_by);
        $this->assertNotNull($originalGroup->fresh()->archived_at);
        $this->assertSame($user->id, $publishedRate->user_id);
        $this->assertSame('Revision Auto Loan', $publishedRate->data['Product']);
        $this->assertSame('5.99%', $publishedRate->data['APR']);
        $this->assertSame('Published Auto Loan', $originalRate->data['Product']);
        $this->assertSame('7.99%', $originalRate->data['APR']);

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

    private function revisionWithRateState(): array
    {
        [$organization, $revision] = $this->revision();

        $user = User::where('organization_id', $organization->id)->first();
        $originalGroup = RateGroup::find($organization->default_rate_group_id);

        Rate::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'uid' => 'auto-loan',
            'rate_group_id' => $originalGroup->id,
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
                'APR' => '5.99%',
            ],
        ]);

        return [$organization, $revision, $originalGroup, $user];
    }
}
