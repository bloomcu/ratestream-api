<?php

namespace Tests\Feature\Base\Invitations;

use DDD\Domain\Base\Invitations\Invitation;
use DDD\Domain\Base\Invitations\Mail\InvitationEmail;
use DDD\Domain\Base\Subscriptions\Plans\Plan;
use DDD\Domain\Base\Users\User;
use DDD\Domain\Organizations\Organization;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InvitationControllerTest extends TestCase
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

        $this->ensureFreePlan();
    }

    /** @test */
    public function public_invitation_show_returns_the_invitation_for_the_requested_organization()
    {
        [$organization, $admin] = $this->organizationWithUser('admin');
        $invitation = $this->invitationForOrganization($organization, $admin, [
            'email' => 'invitee-' . uniqid() . '@example.com',
            'role' => 'editor',
        ]);

        $response = $this->getJson("/api/{$organization->slug}/invitations/{$invitation->uuid}");

        $response->assertOk()
            ->assertJsonPath('data.uuid', (string) $invitation->uuid)
            ->assertJsonPath('data.email', $invitation->email)
            ->assertJsonPath('data.role', 'editor')
            ->assertJsonPath('data.organization.id', $organization->id)
            ->assertJsonPath('data.user.id', $admin->id);
    }

    /** @test */
    public function public_invitation_show_rejects_an_invitation_from_another_organization()
    {
        [$organization] = $this->organizationWithUser('admin');
        [$otherOrganization, $otherAdmin] = $this->organizationWithUser('admin');
        $invitation = $this->invitationForOrganization($otherOrganization, $otherAdmin);

        $response = $this->getJson("/api/{$organization->slug}/invitations/{$invitation->uuid}");

        $response->assertNotFound();
    }

    /** @test */
    public function invitation_index_lists_only_invitations_for_the_requested_organization()
    {
        [$organization, $admin] = $this->organizationWithUser('admin');
        [$otherOrganization, $otherAdmin] = $this->organizationWithUser('admin');
        $invitation = $this->invitationForOrganization($organization, $admin);
        $otherInvitation = $this->invitationForOrganization($otherOrganization, $otherAdmin);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/{$organization->slug}/invitations");

        $response->assertOk()
            ->assertJsonFragment(['uuid' => $invitation->uuid])
            ->assertJsonMissing(['uuid' => $otherInvitation->uuid]);
    }

    /** @test */
    public function organization_users_can_create_invitations_for_their_organization()
    {
        Mail::fake();

        [$organization, $editor] = $this->organizationWithUser('editor');
        $email = 'invitee-' . uniqid() . '@example.com';

        $response = $this->actingAs($editor, 'sanctum')
            ->postJson("/api/{$organization->slug}/invitations", [
                'email' => $email,
                'role' => 'editor',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.email', $email)
            ->assertJsonPath('data.role', 'editor');

        $invitation = Invitation::where('email', $email)->firstOrFail();

        $this->assertSame($organization->id, $invitation->organization_id);
        $this->assertSame($editor->id, $invitation->user_id);

        Mail::assertSent(InvitationEmail::class, function ($mail) use ($email) {
            return $mail->hasTo($email);
        });
    }

    /** @test */
    public function organization_users_cannot_create_invitations_for_another_organization()
    {
        Mail::fake();

        [, $admin] = $this->organizationWithUser('admin');
        [$otherOrganization] = $this->organizationWithUser('admin');
        $email = 'invitee-' . uniqid() . '@example.com';

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/{$otherOrganization->slug}/invitations", [
                'email' => $email,
                'role' => 'editor',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('invitations', [
            'email' => $email,
        ]);
        Mail::assertNothingSent();
    }

    /** @test */
    public function super_admins_can_create_invitations_for_any_organization()
    {
        Mail::fake();

        [, $superAdmin] = $this->organizationWithUser('super_admin');
        [$organization] = $this->organizationWithUser('admin');
        $email = 'invitee-' . uniqid() . '@example.com';

        $response = $this->actingAs($superAdmin, 'sanctum')
            ->postJson("/api/{$organization->slug}/invitations", [
                'email' => $email,
                'role' => 'editor',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.email', $email)
            ->assertJsonPath('data.role', 'editor');

        $this->assertDatabaseHas('invitations', [
            'organization_id' => $organization->id,
            'user_id' => $superAdmin->id,
            'email' => $email,
            'role' => 'editor',
        ]);

        Mail::assertSent(InvitationEmail::class, function ($mail) use ($email) {
            return $mail->hasTo($email);
        });
    }

    /** @test */
    public function invitation_create_rejects_existing_user_and_invitation_emails()
    {
        Mail::fake();

        [$organization, $admin] = $this->organizationWithUser('admin');
        $existingUser = $this->userForOrganization($organization, 'editor');
        $existingInvitation = $this->invitationForOrganization($organization, $admin);

        $userEmailResponse = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/{$organization->slug}/invitations", [
                'email' => $existingUser->email,
                'role' => 'editor',
            ]);

        $invitationEmailResponse = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/{$organization->slug}/invitations", [
                'email' => $existingInvitation->email,
                'role' => 'editor',
            ]);

        $userEmailResponse->assertUnprocessable()
            ->assertJsonValidationErrors('email');
        $invitationEmailResponse->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        Mail::assertNothingSent();
    }

    /** @test */
    public function invitation_delete_removes_an_invitation_from_the_requested_organization()
    {
        [$organization, $admin] = $this->organizationWithUser('admin');
        $invitation = $this->invitationForOrganization($organization, $admin);

        $response = $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/{$organization->slug}/invitations/{$invitation->uuid}");

        $response->assertOk()
            ->assertJsonPath('data.uuid', (string) $invitation->uuid);

        $this->assertDatabaseMissing('invitations', [
            'id' => $invitation->id,
        ]);
    }

    /** @test */
    public function invitation_delete_rejects_an_invitation_from_another_organization()
    {
        [$organization, $admin] = $this->organizationWithUser('admin');
        [$otherOrganization, $otherAdmin] = $this->organizationWithUser('admin');
        $invitation = $this->invitationForOrganization($otherOrganization, $otherAdmin);

        $response = $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/{$organization->slug}/invitations/{$invitation->uuid}");

        $response->assertNotFound();

        $this->assertDatabaseHas('invitations', [
            'id' => $invitation->id,
        ]);
    }

    /** @test */
    public function invitation_registration_uses_the_invitation_email_creates_an_editor_and_consumes_the_invitation()
    {
        [$organization, $admin] = $this->organizationWithUser('admin');
        $invitation = $this->invitationForOrganization($organization, $admin, [
            'email' => 'invited-' . uniqid() . '@example.com',
            'role' => 'admin',
        ]);
        $requestedEmail = 'request-email-' . uniqid() . '@example.com';

        $response = $this->postJson("/api/auth/register/invitation/{$invitation->uuid}", [
            'name' => 'Invited User',
            'email' => $requestedEmail,
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Registration successful')
            ->assertJsonPath('data.name', 'Invited User')
            ->assertJsonPath('data.email', $invitation->email)
            ->assertJsonPath('data.role', 'editor')
            ->assertJsonPath('data.organization.id', $organization->id)
            ->assertJsonStructure([
                'data' => [
                    'access_token',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => $invitation->email,
            'organization_id' => $organization->id,
            'role' => 'editor',
        ]);
        $this->assertDatabaseMissing('users', [
            'email' => $requestedEmail,
        ]);
        $this->assertDatabaseMissing('invitations', [
            'id' => $invitation->id,
        ]);
        $this->assertDatabaseCount('personal_access_tokens', 1);
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

    private function invitationForOrganization(
        Organization $organization,
        User $user,
        array $attributes = []
    ): Invitation {
        return Invitation::create(array_merge([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'email' => 'invitee-' . uniqid() . '@example.com',
            'role' => 'editor',
        ], $attributes));
    }
}
