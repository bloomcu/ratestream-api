<?php

namespace Tests\Feature\Security;

use DDD\Domain\Base\Users\User;
use DDD\Domain\Organizations\Organization;
use DDD\Domain\Rates\RateGroup;
use Tests\TestCase;

class AuthOrganizationAccessBoundaryTest extends TestCase
{
    /** @test */
    public function unauthenticated_users_cannot_access_the_rate_groups_index_route()
    {
        [$organization] = $this->organizationWithUser('admin');

        $this->getJson("/api/{$organization->slug}/rate-groups")
            ->assertUnauthorized();
    }

    /** @test */
    public function editors_can_access_their_own_organizations_rate_groups_index_route()
    {
        [$organization, $editor] = $this->organizationWithUser('editor');
        $group = $this->publishedRateGroup($organization, $editor, 'Editor Group');

        $this->actingAs($editor, 'sanctum')
            ->getJson("/api/{$organization->slug}/rate-groups")
            ->assertOk()
            ->assertJsonPath('0.group.id', $group->id);
    }

    /** @test */
    public function admins_can_access_their_own_organizations_rates_sync_key_show_route()
    {
        [$organization, $admin] = $this->organizationWithUser('admin');

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/{$organization->slug}/rates/sync-key")
            ->assertOk();
    }

    /** @test */
    public function editors_cannot_access_their_own_organizations_rates_sync_key_show_route()
    {
        [$organization, $editor] = $this->organizationWithUser('editor');

        $this->actingAs($editor, 'sanctum')
            ->getJson("/api/{$organization->slug}/rates/sync-key")
            ->assertForbidden();
    }

    /** @test */
    public function users_cannot_access_another_organizations_rate_groups_index_route()
    {
        [$organization, $editor] = $this->organizationWithUser('editor');
        [$otherOrganization, $otherUser] = $this->organizationWithUser('admin');
        $otherGroup = $this->publishedRateGroup($otherOrganization, $otherUser, 'Other Group');

        $this->actingAs($editor, 'sanctum')
            ->getJson("/api/{$otherOrganization->slug}/rate-groups")
            ->assertForbidden();

        $this->assertDatabaseHas('rate_groups', [
            'id' => $otherGroup->id,
            'organization_id' => $otherOrganization->id,
        ]);
    }

    /** @test */
    public function admins_cannot_access_another_organizations_rates_sync_key_show_route()
    {
        [$organization] = $this->organizationWithUser('admin');
        [$otherOrganization, $otherAdmin] = $this->organizationWithUser('admin');

        $this->actingAs($otherAdmin, 'sanctum')
            ->getJson("/api/{$organization->slug}/rates/sync-key")
            ->assertForbidden();
    }

    /** @test */
    public function super_admins_can_access_any_organizations_rate_groups_index_route()
    {
        [$organization, $admin] = $this->organizationWithUser('admin');
        [, $superAdmin] = $this->organizationWithUser('super_admin');
        $group = $this->publishedRateGroup($organization, $admin, 'Shared Group');

        $this->actingAs($superAdmin, 'sanctum')
            ->getJson("/api/{$organization->slug}/rate-groups")
            ->assertOk()
            ->assertJsonPath('0.group.id', $group->id);
    }

    /** @test */
    public function super_admins_can_access_any_organizations_rates_sync_key_show_route()
    {
        [$organization] = $this->organizationWithUser('admin');
        [, $superAdmin] = $this->organizationWithUser('super_admin');

        $this->actingAs($superAdmin, 'sanctum')
            ->getJson("/api/{$organization->slug}/rates/sync-key")
            ->assertOk();
    }

    private function organizationWithUser(?string $role): array
    {
        $uid = uniqid();

        $organization = Organization::create([
            'title' => 'Acme Credit Union ' . $uid,
            'rates_domain' => 'https://' . $uid . '.example.com',
        ]);

        $user = User::create([
            'name' => 'Test User',
            'email' => 'user-' . uniqid() . '@example.com',
            'role' => $role,
            'organization_id' => $organization->id,
            'password' => bcrypt('password'),
        ]);

        return [$organization, $user];
    }

    private function publishedRateGroup(Organization $organization, User $user, string $title): RateGroup
    {
        return RateGroup::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'title' => $title,
            'published_at' => now(),
            'position' => 1,
        ]);
    }
}
