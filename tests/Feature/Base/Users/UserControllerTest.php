<?php

namespace Tests\Feature\Base\Users;

use DDD\Domain\Base\Users\User;
use DDD\Domain\Columns\Column;
use DDD\Domain\Organizations\Organization;
use DDD\Domain\Rates\Rate;
use DDD\Domain\Rates\RateGroup;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    /** @test */
    public function organization_admin_can_delete_a_user_from_their_organization()
    {
        [$organization, $admin] = $this->organizationWithUser('admin');
        $user = $this->userForOrganization($organization, 'editor');

        $response = $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/{$organization->slug}/users/{$user->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $user->id);

        $this->assertSoftDeleted('users', [
            'id' => $user->id,
        ]);
    }

    /** @test */
    public function editor_cannot_delete_a_user_from_their_organization()
    {
        [$organization, $editor] = $this->organizationWithUser('editor');
        $user = $this->userForOrganization($organization, 'editor');

        $response = $this->actingAs($editor, 'sanctum')
            ->deleteJson("/api/{$organization->slug}/users/{$user->id}");

        $response->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
        ]);
    }

    /** @test */
    public function user_without_a_role_cannot_delete_a_user_from_their_organization()
    {
        [$organization, $userWithoutRole] = $this->organizationWithUser(null);
        $user = $this->userForOrganization($organization, 'editor');

        $response = $this->actingAs($userWithoutRole, 'sanctum')
            ->deleteJson("/api/{$organization->slug}/users/{$user->id}");

        $response->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
        ]);
    }

    /** @test */
    public function organization_admin_can_soft_delete_a_user_who_owns_ratestream_records()
    {
        [$organization, $admin] = $this->organizationWithUser('admin');
        $user = $this->userForOrganization($organization, 'editor');
        $rateGroup = RateGroup::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'title' => 'Published',
            'published_at' => now(),
        ]);
        $rate = Rate::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'uid' => 'auto-loan',
            'rate_group_id' => $rateGroup->id,
            'data' => [
                'Product' => 'Auto Loan',
            ],
        ]);
        $column = Column::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'uid' => 'apr',
            'name' => 'APR',
            'rate_group_id' => $rateGroup->id,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/{$organization->slug}/users/{$user->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $user->id);

        $this->assertSoftDeleted('users', [
            'id' => $user->id,
        ]);
        $this->assertDatabaseHas('rate_groups', [
            'id' => $rateGroup->id,
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('rates', [
            'id' => $rate->id,
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('columns', [
            'id' => $column->id,
            'user_id' => $user->id,
        ]);
    }

    /** @test */
    public function admin_cannot_delete_a_user_from_another_organization()
    {
        [$organization, $admin] = $this->organizationWithUser('admin');
        [$otherOrganization] = $this->organizationWithUser('admin');
        $otherUser = $this->userForOrganization($otherOrganization, 'editor');

        $response = $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/{$organization->slug}/users/{$otherUser->id}");

        $response->assertNotFound();

        $this->assertDatabaseHas('users', [
            'id' => $otherUser->id,
        ]);
    }

    /** @test */
    public function super_admin_can_delete_a_user_from_any_organization()
    {
        [$organization] = $this->organizationWithUser('admin');
        [, $superAdmin] = $this->organizationWithUser('super_admin');
        [$otherOrganization] = $this->organizationWithUser('admin');
        $user = $this->userForOrganization($otherOrganization, 'editor');

        $response = $this->actingAs($superAdmin, 'sanctum')
            ->deleteJson("/api/{$organization->slug}/users/{$user->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $user->id);

        $this->assertSoftDeleted('users', [
            'id' => $user->id,
        ]);
    }

    private function organizationWithUser(?string $role): array
    {
        $uid = uniqid();

        $organization = Organization::create([
            'title' => 'Acme Credit Union ' . $uid,
            'rates_domain' => 'https://' . $uid . '.example.com',
        ]);

        return [$organization, $this->userForOrganization($organization, $role)];
    }

    private function userForOrganization(Organization $organization, ?string $role): User
    {
        return User::create([
            'name' => 'Test User',
            'email' => 'user-' . uniqid() . '@example.com',
            'role' => $role,
            'organization_id' => $organization->id,
            'password' => bcrypt('password'),
        ]);
    }
}
