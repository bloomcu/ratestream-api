<?php

namespace Tests\Feature\CSV;

use PHPUnit\Framework\Attributes\Test;
use DDD\Domain\Base\Files\File;
use DDD\Domain\Base\Users\User;
use DDD\Domain\Organizations\Organization;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CSVControllerTest extends TestCase
{
    #[Test]
    public function csv_preview_parses_columns_and_rows_for_the_requested_organization_file()
    {
        Storage::fake();

        [$organization, $admin] = $this->organizationWithAdmin();
        $file = $this->csvFile($organization, $admin, "Unique ID,apr,term\n,APR,Term\nrate-1,5.99%,60\nrate-2,6.49%,72\n");

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/{$organization->slug}/csv/{$file->id}");

        $response->assertOk()
            ->assertJsonPath('columns.0.uid', 'apr')
            ->assertJsonPath('columns.0.name', 'APR')
            ->assertJsonPath('columns.1.uid', 'term')
            ->assertJsonPath('columns.1.name', 'Term')
            ->assertJsonPath('rows.0.uid', 'rate-1')
            ->assertJsonPath('rows.0.data.apr', '5.99%')
            ->assertJsonPath('rows.0.data.term', '60')
            ->assertJsonPath('rows.1.uid', 'rate-2')
            ->assertJsonPath('rows.1.data.apr', '6.49%')
            ->assertJsonPath('rows.1.data.term', '72');
    }

    #[Test]
    public function csv_preview_rejects_duplicate_column_uids()
    {
        Storage::fake();

        [$organization, $admin] = $this->organizationWithAdmin();
        $file = $this->csvFile($organization, $admin, "Unique ID,apr,apr\n,APR,Duplicate APR\nrate-1,5.99%,6.99%\n");

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/{$organization->slug}/csv/{$file->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'There is a problem with this CSV file.')
            ->assertJsonPath('errors.uid.0', 'Duplicate columns found: The CSV contains duplicate columns using the same Unique ID.');
    }

    #[Test]
    public function csv_preview_rejects_a_missing_unique_id_header_cell()
    {
        Storage::fake();

        [$organization, $admin] = $this->organizationWithAdmin();
        $file = $this->csvFile($organization, $admin, "Product,apr\nAuto Loan,APR\nrate-1,5.99%\n");

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/{$organization->slug}/csv/{$file->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'There is a problem with this CSV file.')
            ->assertJsonPath('errors.uid.0', 'Incorrect A1 cell: The first value of the CSV in cell A1 does not contain the term "Unique ID".');
    }

    #[Test]
    public function csv_preview_rejects_a_missing_column_uid()
    {
        Storage::fake();

        [$organization, $admin] = $this->organizationWithAdmin();
        $file = $this->csvFile($organization, $admin, "Unique ID,\n,APR\nrate-1,5.99%\n");

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/{$organization->slug}/csv/{$file->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'There is a problem with this CSV file.')
            ->assertJsonPath('errors.uid.0', 'Missing column Unique ID: The Unique ID is missing on one or more columns.');
    }

    #[Test]
    public function csv_preview_rejects_a_missing_column_name()
    {
        Storage::fake();

        [$organization, $admin] = $this->organizationWithAdmin();
        $file = $this->csvFile($organization, $admin, "Unique ID,apr\n,\nrate-1,5.99%\n");

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/{$organization->slug}/csv/{$file->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'There is a problem with this CSV file.')
            ->assertJsonPath('errors.uid.0', 'Missing column name: The name is missing for one or more columns in the second row of the CSV.');
    }

    #[Test]
    public function csv_preview_rejects_a_missing_row_uid()
    {
        Storage::fake();

        [$organization, $admin] = $this->organizationWithAdmin();
        $file = $this->csvFile($organization, $admin, "Unique ID,apr\n,APR\n,5.99%\n");

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/{$organization->slug}/csv/{$file->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'There is a problem with this CSV file.')
            ->assertJsonPath('errors.uid.0', 'Missing row Unique ID: The Unique ID is missing on one or more rows.');
    }

    #[Test]
    public function csv_preview_rejects_duplicate_row_uids()
    {
        Storage::fake();

        [$organization, $admin] = $this->organizationWithAdmin();
        $file = $this->csvFile($organization, $admin, "Unique ID,apr\n,APR\nrate-1,5.99%\nrate-1,6.49%\n");

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/{$organization->slug}/csv/{$file->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'There is a problem with this CSV file.')
            ->assertJsonPath('errors.uid.0', 'Duplicate rows found: The CSV contains rows using the same Unique ID.');
    }

    private function organizationWithAdmin(): array
    {
        $uid = uniqid();

        $organization = Organization::create([
            'title' => 'Acme Credit Union ' . $uid,
            'rates_domain' => 'https://' . $uid . '.example.com',
        ]);

        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin-' . uniqid() . '@example.com',
            'role' => 'admin',
            'organization_id' => $organization->id,
            'password' => bcrypt('password'),
        ]);

        return [$organization, $admin];
    }

    private function csvFile(Organization $organization, User $user, string $contents): File
    {
        $path = $organization->slug . '/rates-' . uniqid() . '.csv';

        Storage::put($path, $contents);

        return File::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'name' => 'rates',
            'filename' => basename($path),
            'path' => $path,
            'extension' => 'csv',
            'mime' => 'text/csv',
            'disk' => config('filesystems.default'),
        ]);
    }
}
