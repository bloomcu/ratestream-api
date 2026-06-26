<?php

namespace Tests\Feature\Rates;

use DDD\App\Jobs\SyncPublishedRatesToWebsite;
use DDD\Domain\Base\Users\User;
use DDD\Domain\Columns\Column;
use DDD\Domain\Organizations\Organization;
use DDD\Domain\Rates\Rate;
use DDD\Domain\Rates\RateGroup;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RateGroupLifecycleTest extends TestCase
{
    /** @test */
    public function rate_groups_index_lists_only_unarchived_groups_for_the_requested_organization()
    {
        [$organization, $user, $publishedGroup] = $this->organizationWithPublishedGroup();
        [$otherOrganization, $otherUser, $otherGroup] = $this->organizationWithPublishedGroup();

        $draft = $this->revision($organization, $user, $publishedGroup, 'Draft Revision');
        $archived = $this->rateGroup($organization, $user, [
            'title' => 'Archived Group',
            'archived_at' => now(),
            'position' => 3,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/{$organization->slug}/rate-groups");

        $response->assertOk()
            ->assertJsonFragment(['id' => $publishedGroup->id])
            ->assertJsonFragment(['id' => $draft->id])
            ->assertJsonMissing(['id' => $archived->id])
            ->assertJsonMissing(['id' => $otherGroup->id])
            ->assertJsonMissing(['organization_id' => $otherOrganization->id]);
    }

    /** @test */
    public function rate_group_clone_creates_a_draft_revision_with_copied_columns_and_rates()
    {
        [$organization, $user, $publishedGroup] = $this->organizationWithPublishedGroup();

        $this->column($organization, $user, $publishedGroup, 'apr', 'APR', 1);
        $this->rate($organization, $user, $publishedGroup, 'auto-loan', [
            'Product' => 'Auto Loan',
            'APR' => '5.99%',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/{$organization->slug}/rate-groups/{$publishedGroup->id}/clone");

        $response->assertCreated()
            ->assertJsonPath('message', 'Rate group cloned.')
            ->assertJsonPath('group.revision_of', $publishedGroup->id)
            ->assertJsonPath('columns.0.uid', 'apr')
            ->assertJsonPath('rates.0.uid', 'auto-loan');

        $draft = RateGroup::where('organization_id', $organization->id)
            ->where('revision_of', $publishedGroup->id)
            ->firstOrFail();

        $this->assertNull($draft->published_at);
        $this->assertDatabaseHas('columns', [
            'organization_id' => $organization->id,
            'rate_group_id' => $draft->id,
            'uid' => 'apr',
            'name' => 'APR',
        ]);
        $this->assertDatabaseHas('rates', [
            'organization_id' => $organization->id,
            'rate_group_id' => $draft->id,
            'uid' => 'auto-loan',
        ]);
    }

    /** @test */
    public function rate_group_clone_rejects_a_rate_group_from_another_organization()
    {
        [$organization, $user] = $this->organizationWithPublishedGroup();
        [, , $otherGroup] = $this->organizationWithPublishedGroup();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/{$organization->slug}/rate-groups/{$otherGroup->id}/clone");

        $response->assertStatus(422)
            ->assertSeeText('Rate group does not belong to this organization.');
    }

    /** @test */
    public function rate_group_revisions_index_lists_only_revisions_for_the_requested_parent_group()
    {
        [$organization, $user, $publishedGroup] = $this->organizationWithPublishedGroup();
        $anotherPublishedGroup = $this->rateGroup($organization, $user, [
            'title' => 'Another Published Group',
            'published_at' => now(),
            'position' => 2,
        ]);

        $revision = $this->revision($organization, $user, $publishedGroup, 'Draft Revision');
        $unrelatedRevision = $this->revision($organization, $user, $anotherPublishedGroup, 'Other Revision');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/{$organization->slug}/rate-groups/{$publishedGroup->id}/revisions");

        $response->assertOk()
            ->assertJsonFragment(['id' => $revision->id])
            ->assertJsonMissing(['id' => $unrelatedRevision->id]);
    }

    /** @test */
    public function rate_group_revisions_index_rejects_a_parent_group_from_another_organization()
    {
        [$organization, $user] = $this->organizationWithPublishedGroup();
        [, , $otherGroup] = $this->organizationWithPublishedGroup();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/{$organization->slug}/rate-groups/{$otherGroup->id}/revisions");

        $response->assertStatus(422)
            ->assertSeeText('Rate group does not belong to this organization.');
    }

    /** @test */
    public function rate_group_publish_promotes_a_revision_to_the_default_group_and_dispatches_website_sync()
    {
        Queue::fake();

        [$organization, $user, $publishedGroup] = $this->organizationWithPublishedGroup();
        $revision = $this->revision($organization, $user, $publishedGroup, 'Draft Revision');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/{$organization->slug}/rate-groups/{$revision->id}/publish");

        $response->assertOk()
            ->assertJsonPath('message', 'Rate group published.')
            ->assertJsonPath('group.id', $revision->id)
            ->assertJsonPath('group.revision_of', null);

        $this->assertSame($revision->id, $organization->fresh()->default_rate_group_id);
        $this->assertNull($revision->fresh()->revision_of);
        $this->assertNotNull($revision->fresh()->published_at);
        $this->assertSame($revision->id, $publishedGroup->fresh()->superseded_by);
        $this->assertNotNull($publishedGroup->fresh()->archived_at);

        Queue::assertPushed(SyncPublishedRatesToWebsite::class, function ($job) use ($organization, $revision) {
            return $job->organizationId === $organization->id
                && $job->publishedRateGroupId === $revision->id;
        });
        Queue::assertPushed(SyncPublishedRatesToWebsite::class, 1);
    }

    /** @test */
    public function rate_group_publish_rejects_a_non_revision_group()
    {
        Queue::fake();

        [$organization, $user, $publishedGroup] = $this->organizationWithPublishedGroup();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/{$organization->slug}/rate-groups/{$publishedGroup->id}/publish");

        $response->assertStatus(422)
            ->assertSeeText('Only revisions can be published.');

        Queue::assertNotPushed(SyncPublishedRatesToWebsite::class);
    }

    /** @test */
    public function rate_group_publish_rejects_a_rate_group_from_another_organization()
    {
        Queue::fake();

        [$organization, $user] = $this->organizationWithPublishedGroup();
        [$otherOrganization, $otherUser, $otherPublishedGroup] = $this->organizationWithPublishedGroup();
        $otherRevision = $this->revision($otherOrganization, $otherUser, $otherPublishedGroup, 'Other Revision');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/{$organization->slug}/rate-groups/{$otherRevision->id}/publish");

        $response->assertStatus(422)
            ->assertSeeText('Rate group does not belong to this organization.');

        Queue::assertNotPushed(SyncPublishedRatesToWebsite::class);
    }

    /** @test */
    public function rate_group_schedule_stores_a_revision_publish_time_in_utc()
    {
        [$organization, $user, $publishedGroup] = $this->organizationWithPublishedGroup();
        $revision = $this->revision($organization, $user, $publishedGroup, 'Draft Revision');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/{$organization->slug}/rate-groups/{$revision->id}/schedule", [
                'published_at' => '2030-01-02T09:30:00-05:00',
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Rate group scheduled.');

        $storedTime = Carbon::parse($revision->fresh()->published_at, 'UTC')->toIso8601String();

        $this->assertSame('2030-01-02T14:30:00+00:00', $storedTime);
    }

    /** @test */
    public function rate_group_schedule_rejects_invalid_dates()
    {
        [$organization, $user, $publishedGroup] = $this->organizationWithPublishedGroup();
        $revision = $this->revision($organization, $user, $publishedGroup, 'Draft Revision');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/{$organization->slug}/rate-groups/{$revision->id}/schedule", [
                'published_at' => 'not-a-date',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('published_at');

        $this->assertNull($revision->fresh()->published_at);
    }

    /** @test */
    public function rate_group_schedule_rejects_a_non_revision_group()
    {
        [$organization, $user, $publishedGroup] = $this->organizationWithPublishedGroup();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/{$organization->slug}/rate-groups/{$publishedGroup->id}/schedule", [
                'published_at' => now()->addDay()->toIso8601String(),
            ]);

        $response->assertStatus(422)
            ->assertSeeText('Only revisions can be scheduled.');
    }

    /** @test */
    public function rate_group_schedule_rejects_a_rate_group_from_another_organization()
    {
        [$organization, $user] = $this->organizationWithPublishedGroup();
        [$otherOrganization, $otherUser, $otherPublishedGroup] = $this->organizationWithPublishedGroup();
        $otherRevision = $this->revision($otherOrganization, $otherUser, $otherPublishedGroup, 'Other Revision');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/{$organization->slug}/rate-groups/{$otherRevision->id}/schedule", [
                'published_at' => now()->addDay()->toIso8601String(),
            ]);

        $response->assertStatus(422)
            ->assertSeeText('Rate group does not belong to this organization.');
    }

    private function organizationWithPublishedGroup(): array
    {
        $uid = uniqid();

        $organization = Organization::create([
            'title' => 'Acme Credit Union ' . $uid,
            'rates_domain' => 'https://' . $uid . '.example.com',
        ]);

        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin-' . uniqid() . '@example.com',
            'role' => 'admin',
            'organization_id' => $organization->id,
            'password' => bcrypt('password'),
        ]);

        $publishedGroup = $this->rateGroup($organization, $user, [
            'title' => 'Published',
            'published_at' => now(),
            'position' => 1,
        ]);

        $organization->update([
            'default_rate_group_id' => $publishedGroup->id,
        ]);

        return [$organization->fresh(), $user, $publishedGroup];
    }

    private function revision(Organization $organization, User $user, RateGroup $parent, string $title): RateGroup
    {
        return $this->rateGroup($organization, $user, [
            'title' => $title,
            'revision_of' => $parent->id,
            'position' => $parent->position,
        ]);
    }

    private function rateGroup(Organization $organization, User $user, array $attributes): RateGroup
    {
        return RateGroup::create(array_merge([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'title' => 'Rate Group',
        ], $attributes));
    }

    private function column(
        Organization $organization,
        User $user,
        RateGroup $rateGroup,
        string $uid,
        string $name,
        int $order
    ): Column {
        return Column::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'rate_group_id' => $rateGroup->id,
            'uid' => $uid,
            'name' => $name,
            'order' => $order,
        ]);
    }

    private function rate(Organization $organization, User $user, RateGroup $rateGroup, string $uid, array $data): Rate
    {
        return Rate::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'rate_group_id' => $rateGroup->id,
            'uid' => $uid,
            'data' => $data,
        ]);
    }
}
