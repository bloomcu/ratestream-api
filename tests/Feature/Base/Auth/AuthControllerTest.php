<?php

namespace Tests\Feature\Base\Auth;

use DDD\Domain\Base\Users\User;
use DDD\Domain\Base\Subscriptions\Plans\Plan;
use DDD\Domain\Organizations\Organization;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    /** @test */
    public function user_can_login_with_valid_credentials_and_receives_a_sanctum_token()
    {
        [$organization, $user] = $this->organizationWithUser('admin');

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Login successful')
            ->assertJsonPath('data.name', $user->name)
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.organization.id', $organization->id)
            ->assertJsonStructure([
                'data' => [
                    'access_token',
                ],
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'name' => 'auth_token',
        ]);
    }

    /** @test */
    public function login_revokes_existing_tokens_before_issuing_a_new_token()
    {
        [, $user] = $this->organizationWithUser('admin');
        $existingToken = $user->createToken('existing_token');

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $existingToken->accessToken->id,
        ]);
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'name' => 'auth_token',
        ]);
    }

    /** @test */
    public function login_rejects_invalid_credentials()
    {
        [, $user] = $this->organizationWithUser('admin');

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'The given data was invalid.')
            ->assertJsonValidationErrors('credentials');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /** @test */
    public function authenticated_user_can_fetch_their_profile()
    {
        [, $user] = $this->organizationWithUser('editor');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/me');

        $response->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.name', $user->name)
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.role', 'editor')
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token');
    }

    /** @test */
    public function unauthenticated_user_cannot_fetch_their_profile()
    {
        $response = $this->getJson('/api/auth/me');

        $response->assertUnauthorized();
    }

    /** @test */
    public function logout_revokes_the_current_users_tokens()
    {
        [, $user] = $this->organizationWithUser('admin');
        $token = $user->createToken('auth_token');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken)
            ->postJson('/api/auth/logout');

        $response->assertOk()
            ->assertJsonPath('message', 'Tokens Revoked');

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);
    }

    private function organizationWithUser(?string $role): array
    {
        $uid = uniqid();

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
}
