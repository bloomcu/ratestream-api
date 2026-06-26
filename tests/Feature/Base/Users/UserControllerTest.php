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
    public function user_index_returns_only_users_for_the_requested_organization()
    {
        [$organization, $admin] = $this->organizationWithUser('admin');
        $editor = $this->userForOrganization($organization, 'editor');
        [$otherOrganization] = $this->organizationWithUser('admin');
        $otherUser = $this->userForOrganization($otherOrganization, 'editor');

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/{$organization->slug}/users");

        $response->assertOk()
            ->assertJsonFragment(['id' => $admin->id])
            ->assertJsonFragment(['id' => $editor->id])
            ->assertJsonMissing(['id' => $otherUser->id]);
    }

    /** @test */
    public function user_index_returns_only_the_approved_user_fields()
    {
        [$organization, $admin] = $this->organizationWithUser('admin');

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/{$organization->slug}/users");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'email',
                        'role',
                        'created_at',
                    ],
                ],
            ])
            ->assertJsonMissingPath('data.0.password')
            ->assertJsonMissingPath('data.0.remember_token')
            ->assertJsonMissingPath('data.0.organization_id');
    }

    /** @test */
    public function organization_user_cannot_list_users_from_another_organization()
    {
        [, $admin] = $this->organizationWithUser('admin');
        [$otherOrganization] = $this->organizationWithUser('admin');

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/{$otherOrganization->slug}/users");

        $response->assertForbidden();
    }

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

    /** @test */
    public function organization_admin_can_update_an_editor_to_admin_within_their_organization()
    {
        [$organization, $admin] = $this->organizationWithUser('admin');
        $user = $this->userForOrganization($organization, 'editor');

        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/{$organization->slug}/users/{$user->id}/role", [
                'role' => 'admin',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.role', 'admin');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role' => 'admin',
        ]);
    }

    /** @test */
    public function organization_admin_can_update_an_admin_or_editor_to_editor_within_their_organization()
    {
        [$organization, $admin] = $this->organizationWithUser('admin');
        $otherAdmin = $this->userForOrganization($organization, 'admin');
        $editor = $this->userForOrganization($organization, 'editor');

        $adminResponse = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/{$organization->slug}/users/{$otherAdmin->id}/role", [
                'role' => 'editor',
            ]);

        $editorResponse = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/{$organization->slug}/users/{$editor->id}/role", [
                'role' => 'editor',
            ]);

        $adminResponse->assertOk()
            ->assertJsonPath('data.role', 'editor');
        $editorResponse->assertOk()
            ->assertJsonPath('data.role', 'editor');

        $this->assertDatabaseHas('users', [
            'id' => $otherAdmin->id,
            'role' => 'editor',
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $editor->id,
            'role' => 'editor',
        ]);
    }

    /** @test */
    public function organization_admin_cannot_update_a_user_role_from_another_organization()
    {
        [$organization, $admin] = $this->organizationWithUser('admin');
        [$otherOrganization] = $this->organizationWithUser('admin');
        $otherUser = $this->userForOrganization($otherOrganization, 'editor');

        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/{$organization->slug}/users/{$otherUser->id}/role", [
                'role' => 'admin',
            ]);

        $response->assertNotFound();

        $this->assertDatabaseHas('users', [
            'id' => $otherUser->id,
            'role' => 'editor',
        ]);
    }

    /** @test */
    public function editor_cannot_update_user_roles()
    {
        [$organization, $editor] = $this->organizationWithUser('editor');
        $user = $this->userForOrganization($organization, 'editor');

        $response = $this->actingAs($editor, 'sanctum')
            ->patchJson("/api/{$organization->slug}/users/{$user->id}/role", [
                'role' => 'admin',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role' => 'editor',
        ]);
    }

    /** @test */
    public function user_without_a_role_cannot_update_user_roles()
    {
        [$organization, $userWithoutRole] = $this->organizationWithUser(null);
        $user = $this->userForOrganization($organization, 'editor');

        $response = $this->actingAs($userWithoutRole, 'sanctum')
            ->patchJson("/api/{$organization->slug}/users/{$user->id}/role", [
                'role' => 'admin',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role' => 'editor',
        ]);
    }

    /** @test */
    public function admin_cannot_assign_the_super_admin_role()
    {
        [$organization, $admin] = $this->organizationWithUser('admin');
        $user = $this->userForOrganization($organization, 'editor');

        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/{$organization->slug}/users/{$user->id}/role", [
                'role' => 'super_admin',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role' => 'editor',
        ]);
    }

    /** @test */
    public function admin_cannot_modify_an_existing_super_admin()
    {
        [$organization, $admin] = $this->organizationWithUser('admin');
        $superAdmin = $this->userForOrganization($organization, 'super_admin');

        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/{$organization->slug}/users/{$superAdmin->id}/role", [
                'role' => 'editor',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $superAdmin->id,
            'role' => 'super_admin',
        ]);
    }

    /** @test */
    public function super_admin_can_update_a_user_role_across_organizations()
    {
        [$organization] = $this->organizationWithUser('admin');
        [, $superAdmin] = $this->organizationWithUser('super_admin');
        [$otherOrganization] = $this->organizationWithUser('admin');
        $user = $this->userForOrganization($otherOrganization, 'editor');

        $response = $this->actingAs($superAdmin, 'sanctum')
            ->patchJson("/api/{$organization->slug}/users/{$user->id}/role", [
                'role' => 'admin',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.role', 'admin');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role' => 'admin',
        ]);
    }

    /** @test */
    public function invalid_role_returns_a_validation_error()
    {
        [$organization, $admin] = $this->organizationWithUser('admin');
        $user = $this->userForOrganization($organization, 'editor');

        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/{$organization->slug}/users/{$user->id}/role", [
                'role' => 'owner',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('role');
    }

    /** @test */
    public function missing_role_returns_a_validation_error()
    {
        [$organization, $admin] = $this->organizationWithUser('admin');
        $user = $this->userForOrganization($organization, 'editor');

        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/{$organization->slug}/users/{$user->id}/role");

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('role');
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
