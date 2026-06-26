<?php

namespace Tests\Feature\Rates;

use DDD\Domain\Base\Users\User;
use DDD\Domain\Columns\Column;
use DDD\Domain\Organizations\Organization;
use DDD\Domain\Rates\Rate;
use DDD\Domain\Rates\RateGroup;
use Tests\TestCase;

class RateEditingEndpointsTest extends TestCase
{
    /** @test */
    public function rate_batch_rejects_malformed_payloads()
    {
        [$organization, $user, $group] = $this->organizationWithUserAndGroup();

        // Each nested record intentionally omits one required key: rate uid, column name, and delete group_id.
        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/{$organization->slug}/rates/batch", [
                'rate_group_id' => $group->id,
                'rates' => [
                    ['data' => ['APR' => '5.99%']],
                ],
                'columns' => [
                    ['uid' => 'apr'],
                ],
                'deletes' => [
                    ['uid' => 'auto-loan', 'model' => 'rate'],
                ],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'rates.0',
                'columns.0',
                'deletes.0',
            ]);
    }

    /** @test */
    public function rate_batch_rejects_a_rate_group_from_another_organization()
    {
        [$organization, $user] = $this->organizationWithUserAndGroup();
        [, , $otherGroup] = $this->organizationWithUserAndGroup();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/{$organization->slug}/rates/batch", [
                'rate_group_id' => $otherGroup->id,
                'rates' => [],
                'columns' => [],
                'deletes' => [],
            ]);

        $response->assertStatus(422)
            ->assertSeeText('Invalid rate group for this organization.');
    }

    /** @test */
    public function column_store_creates_a_column_for_the_requested_organizations_rate_group()
    {
        [$organization, $user, $group] = $this->organizationWithUserAndGroup();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/{$organization->slug}/columns", [
                'name' => 'APR',
                'rate_group_id' => $group->id,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'APR');

        $column = Column::where('organization_id', $organization->id)
            ->where('rate_group_id', $group->id)
            ->where('name', 'APR')
            ->firstOrFail();

        $this->assertSame($user->id, $column->user_id);
        $this->assertNull($column->uid);
    }

    /** @test */
    public function column_order_update_reorders_columns_within_the_organization()
    {
        [$organization, $user, $group] = $this->organizationWithUserAndGroup();

        $first = Column::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'rate_group_id' => $group->id,
            'uid' => 'apr',
            'name' => 'APR',
        ]);

        $second = Column::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'rate_group_id' => $group->id,
            'uid' => 'term',
            'name' => 'Term',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/{$organization->slug}/columns/{$second->id}/order", [
                'order' => 1,
            ]);

        $response->assertOk();

        $this->assertSame(1, $second->fresh()->order);
        $this->assertSame(2, $first->fresh()->order);
    }

    /** @test */
    public function rate_uid_update_changes_the_uid_for_a_rate_in_the_same_organization()
    {
        [$organization, $user, $group] = $this->organizationWithUserAndGroup();

        $rate = Rate::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'rate_group_id' => $group->id,
            'uid' => 'auto-loan',
            'data' => ['APR' => '5.99%'],
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/{$organization->slug}/rates/uid/update/{$rate->uid}", [
                'uid' => 'updated-auto-loan',
            ]);

        $response->assertOk();

        $this->assertSame('updated-auto-loan', $rate->fresh()->uid);
    }

    /** @test */
    public function rate_uid_update_rejects_a_duplicate_uid_within_the_same_organization()
    {
        [$organization, $user, $group] = $this->organizationWithUserAndGroup();

        Rate::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'rate_group_id' => $group->id,
            'uid' => 'existing-rate',
            'data' => ['APR' => '5.99%'],
        ]);

        $rate = Rate::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'rate_group_id' => $group->id,
            'uid' => 'candidate-rate',
            'data' => ['APR' => '4.99%'],
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/{$organization->slug}/rates/uid/update/{$rate->uid}", [
                'uid' => 'existing-rate',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('uid');

        $this->assertSame('candidate-rate', $rate->fresh()->uid);
    }

    private function organizationWithUserAndGroup(): array
    {
        $uid = uniqid();

        $organization = Organization::create([
            'title' => 'Acme Credit Union ' . $uid,
            'rates_domain' => 'https://' . $uid . '.example.com',
        ]);

        $user = User::create([
            'name' => 'Test User',
            'email' => 'user-' . uniqid() . '@example.com',
            'role' => 'admin',
            'organization_id' => $organization->id,
            'password' => bcrypt('password'),
        ]);

        $group = RateGroup::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'title' => 'Published',
            'published_at' => now(),
            'position' => 1,
        ]);

        $organization->update([
            'default_rate_group_id' => $group->id,
        ]);

        return [$organization, $user, $group];
    }
}
