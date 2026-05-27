<?php

namespace Tests\Feature\Rates;

use DDD\Domain\Base\Users\User;
use DDD\Domain\Organizations\Organization;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RateSyncKeyControllerTest extends TestCase
{
    /** @test */
    public function admin_get_creates_and_returns_a_rates_sync_key_when_missing()
    {
        [$organization, $admin] = $this->organizationWithUser('admin');

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/{$organization->slug}/rates/sync-key");

        $response->assertOk()
            ->assertJsonPath('data.created', true);

        $key = $response->json('data.key');
        $rawStoredKey = DB::table('organizations')
            ->where('id', $organization->id)
            ->value('rates_sync_key');

        $this->assertIsString($key);
        $this->assertSame(64, strlen($key));
        $this->assertSame($key, $organization->fresh()->rates_sync_key);
        $this->assertNotSame($key, $rawStoredKey);
    }

    /** @test */
    public function admin_get_returns_the_existing_rates_sync_key()
    {
        [$organization, $admin] = $this->organizationWithUser('admin', [
            'rates_sync_key' => 'existing-sync-key',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/{$organization->slug}/rates/sync-key");

        $response->assertOk()
            ->assertJsonPath('data.key', 'existing-sync-key')
            ->assertJsonPath('data.created', false);

        $this->assertSame('existing-sync-key', $organization->fresh()->rates_sync_key);
    }

    /** @test */
    public function admin_can_rotate_the_rates_sync_key()
    {
        [$organization, $admin] = $this->organizationWithUser('admin', [
            'rates_sync_key' => 'existing-sync-key',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/{$organization->slug}/rates/sync-key/rotate");

        $response->assertOk()
            ->assertJsonPath('message', 'Rates sync key rotated.');

        $key = $response->json('data.key');

        $this->assertIsString($key);
        $this->assertSame(64, strlen($key));
        $this->assertNotSame('existing-sync-key', $key);
        $this->assertSame($key, $organization->fresh()->rates_sync_key);
    }

    /** @test */
    public function super_admin_can_manage_any_organizations_rates_sync_key()
    {
        [$organization] = $this->organizationWithUser('admin');
        [, $superAdmin] = $this->organizationWithUser('super_admin');

        $response = $this->actingAs($superAdmin, 'sanctum')
            ->getJson("/api/{$organization->slug}/rates/sync-key");

        $response->assertOk()
            ->assertJsonPath('data.created', true);
    }

    /** @test */
    public function non_admin_organization_user_cannot_manage_the_rates_sync_key()
    {
        [$organization, $editor] = $this->organizationWithUser('editor');

        $response = $this->actingAs($editor, 'sanctum')
            ->getJson("/api/{$organization->slug}/rates/sync-key");

        $response->assertForbidden();
    }

    /** @test */
    public function user_without_a_role_cannot_manage_the_rates_sync_key()
    {
        [$organization, $userWithoutRole] = $this->organizationWithUser(null);

        $response = $this->actingAs($userWithoutRole, 'sanctum')
            ->getJson("/api/{$organization->slug}/rates/sync-key");

        $response->assertForbidden();
    }

    /** @test */
    public function admin_from_another_organization_cannot_manage_the_rates_sync_key()
    {
        [$organization] = $this->organizationWithUser('admin');
        [, $otherAdmin] = $this->organizationWithUser('admin');

        $response = $this->actingAs($otherAdmin, 'sanctum')
            ->getJson("/api/{$organization->slug}/rates/sync-key");

        $response->assertForbidden();
    }

    private function organizationWithUser(?string $role, array $organizationAttributes = []): array
    {
        $uid = uniqid();

        $organization = Organization::create(array_merge([
            'title' => 'Acme Credit Union ' . $uid,
            'rates_domain' => 'https://' . $uid . '.example.com',
        ], $organizationAttributes));

        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin-' . uniqid() . '@example.com',
            'role' => $role,
            'organization_id' => $organization->id,
            'password' => bcrypt('password'),
        ]);

        return [$organization, $user];
    }
}
