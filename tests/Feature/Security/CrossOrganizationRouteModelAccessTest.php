<?php

namespace Tests\Feature\Security;

use PHPUnit\Framework\Attributes\Test;
use DDD\Domain\Base\Comments\Comment;
use DDD\Domain\Base\Files\File;
use DDD\Domain\Base\Media\Media;
use DDD\Domain\Base\Users\User;
use DDD\Domain\Columns\Column;
use DDD\Domain\Organizations\Organization;
use DDD\Domain\Rates\Rate;
use DDD\Domain\Rates\RateGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CrossOrganizationRouteModelAccessTest extends TestCase
{
    #[Test]
    public function organization_admin_cannot_update_another_organizations_rate_uid()
    {
        [$organization, $admin] = $this->organizationWithAdmin('Requester CU');
        [$otherOrganization, $otherUser] = $this->organizationWithAdmin('Target CU');

        $otherGroup = RateGroup::create([
            'organization_id' => $otherOrganization->id,
            'user_id' => $otherUser->id,
            'title' => 'Published',
            'published_at' => now(),
        ]);

        $otherRate = Rate::create([
            'organization_id' => $otherOrganization->id,
            'user_id' => $otherUser->id,
            'uid' => 'target-rate',
            'rate_group_id' => $otherGroup->id,
            'data' => ['APR' => '7.99%'],
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/{$organization->slug}/rates/uid/update/{$otherRate->uid}", [
                'uid' => 'hijacked-rate',
            ]);

        $response->assertNotFound();
        $this->assertSame('target-rate', $otherRate->fresh()->uid);
    }

    #[Test]
    public function organization_admin_cannot_reorder_another_organizations_column()
    {
        [$organization, $admin] = $this->organizationWithAdmin('Requester CU');
        [$otherOrganization, $otherUser] = $this->organizationWithAdmin('Target CU');

        $otherColumn = Column::create([
            'organization_id' => $otherOrganization->id,
            'user_id' => $otherUser->id,
            'uid' => 'target-column',
            'name' => 'Target Column',
            'order' => 1,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/{$organization->slug}/columns/{$otherColumn->id}/order", [
                'order' => 2,
            ]);

        $response->assertNotFound();
        $this->assertSame(1, $otherColumn->fresh()->order);
    }

    #[Test]
    public function organization_admin_cannot_preview_another_organizations_csv_file()
    {
        Storage::fake();

        [$organization, $admin] = $this->organizationWithAdmin('Requester CU');
        [$otherOrganization, $otherUser] = $this->organizationWithAdmin('Target CU');

        Storage::put('target/rates.csv', "Unique ID,apr\n,APR\nrate-1,5.99%\n");

        $otherFile = File::create([
            'organization_id' => $otherOrganization->id,
            'user_id' => $otherUser->id,
            'name' => 'rates',
            'filename' => 'rates.csv',
            'path' => 'target/rates.csv',
            'extension' => 'csv',
            'mime' => 'text/csv',
            'disk' => config('filesystems.default'),
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/{$organization->slug}/csv/{$otherFile->id}");

        $response->assertNotFound();
    }

    #[Test]
    public function organization_admin_cannot_delete_another_organizations_comment()
    {
        [$organization, $admin] = $this->organizationWithAdmin('Requester CU');
        [$otherOrganization, $otherUser] = $this->organizationWithAdmin('Target CU');

        $otherCommentId = DB::table('comments')->insertGetId([
            'user_id' => $otherUser->id,
            'body' => 'Target organization comment',
            'commentable_id' => $otherOrganization->id,
            'commentable_type' => Organization::class,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $otherComment = Comment::findOrFail($otherCommentId);

        $response = $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/organizations/{$organization->slug}/comments/{$otherComment->id}");

        $response->assertNotFound();
        $this->assertDatabaseHas('comments', [
            'id' => $otherComment->id,
        ]);
    }

    #[Test]
    public function organization_admin_cannot_delete_another_organizations_media()
    {
        [$organization, $admin] = $this->organizationWithAdmin('Requester CU');
        [$otherOrganization, $otherUser] = $this->organizationWithAdmin('Target CU');

        $otherMediaId = DB::table('media')->insertGetId([
            'model_type' => Organization::class,
            'model_id' => $otherOrganization->id,
            'user_id' => $otherUser->id,
            'uuid' => (string) Str::uuid(),
            'collection_name' => 'default',
            'name' => 'target-media',
            'file_name' => 'target-media.jpg',
            'mime_type' => 'image/jpeg',
            'disk' => config('filesystems.default'),
            'conversions_disk' => config('filesystems.default'),
            'size' => 1024,
            'manipulations' => json_encode([]),
            'custom_properties' => json_encode([]),
            'generated_conversions' => json_encode([]),
            'responsive_images' => json_encode([]),
            'order_column' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $otherMedia = Media::findOrFail($otherMediaId);

        $response = $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/{$organization->slug}/media/{$otherMedia->id}");

        $response->assertNotFound();
        $this->assertDatabaseHas('media', [
            'id' => $otherMedia->id,
        ]);
    }

    private function organizationWithAdmin(string $title): array
    {
        $uid = uniqid();

        $organization = Organization::create([
            'title' => $title . ' ' . $uid,
            'rates_domain' => 'https://' . $uid . '.example.com',
        ]);

        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin-' . $uid . '@example.com',
            'role' => 'admin',
            'organization_id' => $organization->id,
            'password' => bcrypt('password'),
        ]);

        return [$organization, $admin];
    }
}
