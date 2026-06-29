<?php

namespace Tests\Feature\Base\Organizations;

use DDD\Domain\Base\Organizations\Organization;
use DDD\Domain\Base\Subscriptions\Plans\Plan;
use DDD\Domain\Base\Users\User;
use DDD\Domain\Rates\RateGroup;
use Tests\TestCase;

class OrganizationControllerTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->ensureFreePlan();
    }

    /** @test */
    public function unauthenticated_users_cannot_access_organization_routes()
    {
        $organization = $this->organization();

        $this->getJson('/api/organizations')->assertUnauthorized();
        $this->getJson("/api/organizations/{$organization->slug}")->assertUnauthorized();
        $this->putJson("/api/organizations/{$organization->slug}", [
            'title' => 'Updated Credit Union',
        ])->assertUnauthorized();
        $this->deleteJson("/api/organizations/{$organization->slug}")->assertUnauthorized();
    }

    /** @test */
    public function organization_index_returns_only_the_authenticated_users_organization()
    {
        [$organization, $admin] = $this->organizationWithUser('admin');
        [$otherOrganization] = $this->organizationWithUser('admin');

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/organizations');

        $response->assertOk()
            ->assertJsonFragment(['id' => $organization->id])
            ->assertJsonMissing(['id' => $otherOrganization->id]);
    }

    /** @test */
    public function super_admins_can_list_all_organizations()
    {
        $middleOrganization = $this->organization('Middle Credit Union');
        $superAdmin = $this->userForOrganization($middleOrganization, 'super_admin');
        $zetaOrganization = $this->organization('Zeta Credit Union');
        $alphaOrganization = $this->organization('Alpha Credit Union');

        $response = $this->actingAs($superAdmin, 'sanctum')
            ->getJson('/api/organizations');

        $response->assertOk()
            ->assertJsonFragment(['id' => $zetaOrganization->id])
            ->assertJsonFragment(['id' => $alphaOrganization->id]);

        $titles = collect($response->json('data'))->pluck('title')->all();

        $this->assertSame([
            'Alpha Credit Union',
            'Middle Credit Union',
            'Zeta Credit Union',
        ], $titles);
    }

    /** @test */
    public function users_can_show_their_own_organization_by_slug()
    {
        [$organization, $admin] = $this->organizationWithUser('admin');

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/organizations/{$organization->slug}");

        $response->assertOk()
            ->assertJsonPath('data.id', $organization->id)
            ->assertJsonPath('data.slug', $organization->slug);
    }

    /** @test */
    public function users_cannot_show_another_organization_by_slug()
    {
        [, $admin] = $this->organizationWithUser('admin');
        [$otherOrganization] = $this->organizationWithUser('admin');

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/organizations/{$otherOrganization->slug}");

        $response->assertForbidden();
    }

    /** @test */
    public function super_admins_can_show_any_organization_by_slug()
    {
        [, $superAdmin] = $this->organizationWithUser('super_admin');
        [$organization] = $this->organizationWithUser('admin');

        $response = $this->actingAs($superAdmin, 'sanctum')
            ->getJson("/api/organizations/{$organization->slug}");

        $response->assertOk()
            ->assertJsonPath('data.id', $organization->id);
    }

    /** @test */
    public function users_can_update_their_own_organization_title_and_rates_domain()
    {
        [$organization, $admin] = $this->organizationWithUser('admin');

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/organizations/{$organization->slug}", [
                'title' => 'Updated Credit Union',
                'rates_domain' => 'http://host.docker.internal:8080',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $organization->id)
            ->assertJsonPath('data.title', 'Updated Credit Union')
            ->assertJsonPath('data.rates_domain', 'http://host.docker.internal:8080');

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'title' => 'Updated Credit Union',
            'rates_domain' => 'http://host.docker.internal:8080',
        ]);
    }

    /** @test */
    public function users_cannot_update_another_organization()
    {
        [, $admin] = $this->organizationWithUser('admin');
        [$otherOrganization] = $this->organizationWithUser('admin');

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/organizations/{$otherOrganization->slug}", [
                'title' => 'Unauthorized Update',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('organizations', [
            'id' => $otherOrganization->id,
            'title' => 'Unauthorized Update',
        ]);
    }

    /** @test */
    public function authenticated_users_can_create_an_organization_with_a_default_rate_group()
    {
        [, $admin] = $this->organizationWithUser('admin');

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/organizations', [
                'title' => 'New Credit Union',
                'rates_domain' => 'https://rates.example.com',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'New Credit Union')
            ->assertJsonPath('data.rates_domain', 'https://rates.example.com')
            ->assertJsonPath('data.default_rate_group_id', fn ($id) => is_int($id));

        $organization = Organization::where('title', 'New Credit Union')->firstOrFail();
        $rateGroup = RateGroup::findOrFail($organization->default_rate_group_id);

        $this->assertSame($organization->id, $rateGroup->organization_id);
        $this->assertSame('Default', $rateGroup->title);
        $this->assertNotNull($rateGroup->published_at);
    }

    /** @test */
    public function users_cannot_delete_another_organization()
    {
        [, $admin] = $this->organizationWithUser('admin');
        [$otherOrganization] = $this->organizationWithUser('admin');

        $response = $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/organizations/{$otherOrganization->slug}");

        $response->assertForbidden();

        $this->assertDatabaseHas('organizations', [
            'id' => $otherOrganization->id,
        ]);
    }

    /** @test */
    public function super_admins_can_delete_an_empty_organization()
    {
        [, $superAdmin] = $this->organizationWithUser('super_admin');
        $organization = $this->organization('Empty Credit Union');

        $response = $this->actingAs($superAdmin, 'sanctum')
            ->deleteJson("/api/organizations/{$organization->slug}");

        $response->assertOk()
            ->assertJsonPath('data.id', $organization->id);

        $this->assertDatabaseMissing('organizations', [
            'id' => $organization->id,
        ]);
    }

    private function organizationWithUser(?string $role): array
    {
        $organization = $this->organization();

        return [$organization, $this->userForOrganization($organization, $role)];
    }

    private function organization(?string $title = null): Organization
    {
        $uid = uniqid();

        return Organization::create([
            'title' => $title ?? 'Acme Credit Union ' . $uid,
            'rates_domain' => 'https://' . $uid . '.example.com',
        ]);
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

    private function ensureFreePlan(): void
    {
        Plan::firstOrCreate(
            ['buyable' => false],
            [
                'title' => 'Free',
                'slug' => 'free',
                'price' => 0,
                'interval' => null,
                'limits' => [],
            ]
        );
    }
}
