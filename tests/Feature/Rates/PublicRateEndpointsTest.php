<?php

namespace Tests\Feature\Rates;

use PHPUnit\Framework\Attributes\Test;
use DDD\Domain\Base\Users\User;
use DDD\Domain\Columns\Column;
use DDD\Domain\Organizations\Organization;
use DDD\Domain\Rates\Rate;
use DDD\Domain\Rates\RateGroup;
use Tests\TestCase;

class PublicRateEndpointsTest extends TestCase
{
    #[Test]
    public function public_rates_index_returns_the_default_rate_group_for_the_organization()
    {
        [$organization, $user] = $this->organizationWithUser();
        $defaultGroup = $this->rateGroup($organization, $user, 'Published');
        $draftGroup = $this->rateGroup($organization, $user, 'Draft Revision');
        $organization->update(['default_rate_group_id' => $defaultGroup->id]);

        $defaultColumn = $this->column($organization, $user, $defaultGroup, 'apr', 'APR', 1);
        $draftColumn = $this->column($organization, $user, $draftGroup, 'draft_apr', 'Draft APR', 1);

        $defaultRate = $this->rate($organization, $user, $defaultGroup, 'auto-loan', ['apr' => '5.99%']);
        $draftRate = $this->rate($organization, $user, $draftGroup, 'draft-auto-loan', ['draft_apr' => '9.99%']);

        $response = $this->getJson("/api/{$organization->slug}/rates");

        $response->assertOk()
            ->assertJsonPath('columns.0.uid', $defaultColumn->uid)
            ->assertJsonPath('columns.0.name', $defaultColumn->name)
            ->assertJsonPath('rates.0.uid', $defaultRate->uid)
            ->assertJsonPath('rates.0.data.apr', '5.99%')
            // Draft-only column and rate UIDs must be absent from the public default-group payload.
            ->assertJsonMissing([
                'uid' => $draftColumn->uid,
            ])
            ->assertJsonMissing([
                'uid' => $draftRate->uid,
            ]);
    }

    #[Test]
    public function public_rates_index_can_return_an_explicit_rate_group_for_the_same_organization()
    {
        [$organization, $user] = $this->organizationWithUser();
        $defaultGroup = $this->rateGroup($organization, $user, 'Published');
        $revisionGroup = $this->rateGroup($organization, $user, 'Revision');
        $organization->update(['default_rate_group_id' => $defaultGroup->id]);

        $this->column($organization, $user, $defaultGroup, 'apr', 'APR', 1);
        $revisionColumn = $this->column($organization, $user, $revisionGroup, 'term', 'Term', 1);

        $this->rate($organization, $user, $defaultGroup, 'auto-loan', ['apr' => '5.99%']);
        $revisionRate = $this->rate($organization, $user, $revisionGroup, 'share-loan', ['term' => '60']);

        // An explicit in-organization rate_group_id should override the default group selection.
        $response = $this->getJson("/api/{$organization->slug}/rates?rate_group_id={$revisionGroup->id}");

        $response->assertOk()
            ->assertJsonPath('columns.0.uid', $revisionColumn->uid)
            ->assertJsonPath('rates.0.uid', $revisionRate->uid)
            ->assertJsonPath('rates.0.data.term', '60')
            ->assertJsonMissing([
                'uid' => 'auto-loan',
            ]);
    }

    #[Test]
    public function public_rates_index_rejects_a_rate_group_from_another_organization()
    {
        [$organization] = $this->organizationWithUser();
        [$otherOrganization, $otherUser] = $this->organizationWithUser();
        $otherGroup = $this->rateGroup($otherOrganization, $otherUser, 'Other Group');

        $response = $this->getJson("/api/{$organization->slug}/rates?rate_group_id={$otherGroup->id}");

        $response->assertStatus(422)
            ->assertSeeText('Invalid rate group for this organization.');
    }

    #[Test]
    public function public_rates_export_streams_csv_for_the_default_rate_group()
    {
        [$organization, $user] = $this->organizationWithUser();
        $defaultGroup = $this->rateGroup($organization, $user, 'Published');
        $draftGroup = $this->rateGroup($organization, $user, 'Draft');
        $organization->update(['default_rate_group_id' => $defaultGroup->id]);

        $this->column($organization, $user, $defaultGroup, 'apr', 'APR', 1);
        $this->column($organization, $user, $defaultGroup, 'term', 'Term', 2);
        $this->column($organization, $user, $draftGroup, 'draft_col', 'Draft Column', 1);

        $this->rate($organization, $user, $defaultGroup, 'auto-loan', ['apr' => '5.99%', 'term' => '60']);
        $this->rate($organization, $user, $draftGroup, 'draft-rate', ['draft_col' => 'hidden']);

        $response = $this->get("/api/{$organization->slug}/rates/export");

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=utf-8');
        $response->assertHeader('content-disposition', "attachment; filename={$organization->slug}-rates.csv");

        $csv = $response->streamedContent();

        $this->assertStringContainsString("\"Unique ID\",apr,term\n", $csv);
        $this->assertStringContainsString(",APR,Term\n", $csv);
        $this->assertStringContainsString("auto-loan,5.99%,60\n", $csv);
        $this->assertStringNotContainsString('draft-rate', $csv);
        $this->assertStringNotContainsString('draft_col', $csv);
    }

    private function organizationWithUser(): array
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

        return [$organization, $user];
    }

    private function rateGroup(Organization $organization, User $user, string $title): RateGroup
    {
        return RateGroup::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'title' => $title,
            'published_at' => now(),
            'position' => 1,
        ]);
    }

    private function column(Organization $organization, User $user, RateGroup $rateGroup, string $uid, string $name, int $order): Column
    {
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
