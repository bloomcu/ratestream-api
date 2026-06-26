<?php

namespace Tests\Feature\Base\Auth;

use DDD\Domain\Base\Invitations\Invitation;
use DDD\Domain\Base\Subscriptions\Plans\Plan;
use DDD\Domain\Base\Users\User;
use DDD\Domain\Organizations\Organization;
use DDD\Domain\Rates\RateGroup;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->app->instance(UncompromisedVerifier::class, new class implements UncompromisedVerifier {
            public function verify($data)
            {
                return true;
            }
        });
    }

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

    /** @test */
    public function registration_creates_an_admin_user_and_organization()
    {
        [$registerResponse, $payload] = $this->registerUser();

        $registerResponse->assertOk()
            ->assertJsonPath('message', 'Registration successful')
            ->assertJsonPath('data.name', $payload['name'])
            ->assertJsonPath('data.email', $payload['email'])
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.organization.title', $payload['organization_title'])
            ->assertJsonStructure([
                'data' => [
                    'access_token',
                    'organization' => [
                        'id',
                        'default_rate_group_id',
                    ],
                ],
            ]);

        $user = User::where('email', $payload['email'])->firstOrFail();
        $organization = Organization::where('title', $payload['organization_title'])->firstOrFail();

        $this->assertSame($organization->id, $user->organization_id);
        $this->assertSame('admin', $user->role->value);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    /** @test */
    public function registration_creates_a_default_rate_group_for_the_new_organization()
    {
        $rateGroupCountBeforeRegistration = RateGroup::count();
        [, $payload] = $this->registerUser();

        $organization = Organization::where('title', $payload['organization_title'])->firstOrFail();
        $user = User::where('email', $payload['email'])->firstOrFail();
        $defaultRateGroup = RateGroup::findOrFail($organization->default_rate_group_id);

        $this->assertSame($organization->id, $defaultRateGroup->organization_id);
        $this->assertSame($user->id, $defaultRateGroup->user_id);
        $this->assertSame('Default', $defaultRateGroup->title);
        $this->assertNotNull($defaultRateGroup->published_at);
        $this->assertSame($rateGroupCountBeforeRegistration + 1, RateGroup::count());
    }

    /** @test */
    public function registration_token_can_fetch_the_authenticated_profile()
    {
        [$registerResponse, $payload] = $this->registerUser();
        $user = User::where('email', $payload['email'])->firstOrFail();
        $registrationToken = $registerResponse->json('data.access_token');

        $this->withHeader('Authorization', 'Bearer ' . $registrationToken)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $payload['email']);
    }

    /** @test */
    public function logout_revokes_a_registration_token()
    {
        [$registerResponse] = $this->registerUser();
        $registrationToken = $registerResponse->json('data.access_token');

        $this->withHeader('Authorization', 'Bearer ' . $registrationToken)
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Tokens Revoked');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /** @test */
    public function user_can_log_in_after_logging_out_of_the_registration_token()
    {
        [$registerResponse, $payload] = $this->registerUser();
        $organization = Organization::where('title', $payload['organization_title'])->firstOrFail();
        $registrationToken = $registerResponse->json('data.access_token');

        $this->withHeader('Authorization', 'Bearer ' . $registrationToken)
            ->postJson('/api/auth/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->flushHeaders();
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => $payload['email'],
            'password' => $payload['password'],
        ]);

        $loginResponse->assertOk()
            ->assertJsonPath('message', 'Login successful')
            ->assertJsonPath('data.email', $payload['email'])
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.organization.id', $organization->id);

        $loginToken = $loginResponse->json('data.access_token');

        $this->assertNotSame($registrationToken, $loginToken);
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->withHeader('Authorization', 'Bearer ' . $loginToken)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $payload['email']);
    }

    /** @test */
    public function registration_token_cannot_be_used_after_logout()
    {
        [$registerResponse] = $this->registerUser();
        $registrationToken = $registerResponse->json('data.access_token');

        $this->withHeader('Authorization', 'Bearer ' . $registrationToken)
            ->postJson('/api/auth/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->flushHeaders();
        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer ' . $registrationToken)
            ->getJson('/api/auth/me')
            ->assertUnauthorized();
    }

    /** @test */
    public function password_forgot_returns_the_same_response_for_known_and_unknown_valid_email_addresses()
    {
        [, $user] = $this->organizationWithUser('admin');

        $knownResponse = $this->postJson('/api/auth/password/forgot', [
            'email' => $user->email,
        ]);

        $unknownResponse = $this->postJson('/api/auth/password/forgot', [
            'email' => 'missing-' . uniqid() . '@example.com',
        ]);

        $knownResponse->assertOk()
            ->assertJsonPath('message', 'If this is a valid account email, you will recieve a password reset email.');
        $unknownResponse->assertOk()
            ->assertJsonPath('message', 'If this is a valid account email, you will recieve a password reset email.');

        $this->assertDatabaseHas('password_resets', [
            'email' => $user->email,
        ]);
    }

    /** @test */
    public function password_reset_accepts_a_valid_token_updates_the_password_and_revokes_existing_tokens()
    {
        [, $user] = $this->organizationWithUser('admin');
        $existingToken = $user->createToken('existing_token');
        $newPassword = 'N3wP@ssword-' . uniqid() . '-Aa!';
        $resetToken = Password::broker()->createToken($user);

        $response = $this->postJson('/api/auth/password/reset', [
            'token' => $resetToken,
            'email' => $user->email,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Password successfully reset.');

        $this->assertTrue(Hash::check($newPassword, $user->fresh()->password));
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $existingToken->accessToken->id,
        ]);
        $this->assertDatabaseMissing('password_resets', [
            'email' => $user->email,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertUnprocessable();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => $newPassword,
        ])->assertOk();
    }

    /** @test */
    public function password_reset_rejects_an_invalid_token()
    {
        [, $user] = $this->organizationWithUser('admin');
        $newPassword = 'N3wP@ssword-' . uniqid() . '-Aa!';

        $response = $this->postJson('/api/auth/password/reset', [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'There was a problem resetting the password.')
            ->assertJsonValidationErrors('password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    /** @test */
    public function registration_rejects_an_existing_user_email()
    {
        [, $existingUser] = $this->organizationWithUser('admin');
        $password = 'R@teStream-' . uniqid() . '-Aa1!';

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Duplicate User',
            'email' => $existingUser->email,
            'organization_title' => 'Duplicate Credit Union',
            'password' => $password,
            'password_confirmation' => $password,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('organizations', 1);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /** @test */
    public function registration_rejects_an_email_with_a_pending_invitation()
    {
        [$organization, $invitingUser] = $this->organizationWithUser('admin');
        $email = 'invited-' . uniqid() . '@example.com';
        $password = 'R@teStream-' . uniqid() . '-Aa1!';

        Invitation::create([
            'organization_id' => $organization->id,
            'user_id' => $invitingUser->id,
            'email' => $email,
            'role' => 'editor',
        ]);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Invited User',
            'email' => $email,
            'organization_title' => 'Invited Credit Union',
            'password' => $password,
            'password_confirmation' => $password,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('organizations', 1);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('invitations', 1);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    private function organizationWithUser(?string $role): array
    {
        $uid = uniqid();

        $this->ensureFreePlan();

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

    private function registerUser(array $overrides = []): array
    {
        $this->ensureFreePlan();

        $payload = array_merge([
            'name' => 'New User',
            'email' => 'new-user-' . uniqid() . '@example.com',
            'organization_title' => 'New Credit Union ' . uniqid(),
            'password' => 'R@teStream-' . uniqid() . '-Aa1!',
        ], $overrides);

        $payload['password_confirmation'] = $payload['password'];

        return [$this->postJson('/api/auth/register', $payload), $payload];
    }
}
