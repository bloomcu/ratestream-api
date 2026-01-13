<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Models
use DDD\Domain\Organizations\Organization;
use DDD\Domain\Rates\RateGroup;
use DDD\Domain\Rates\Rate;
use DDD\Domain\Columns\Column;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Backfill a default rate group per organization and attach existing rates/columns.
     *
     * @return void
     */
    public function up()
    {
        Organization::chunkById(100, function ($organizations) {
            foreach ($organizations as $organization) {
                DB::transaction(function () use ($organization) {
                    // Create default group if missing
                    $ownerId = $organization->users()->orderBy('id')->value('id');

                    $group = RateGroup::firstOrCreate(
                        [
                            'organization_id' => $organization->id,
                            'title' => 'Default',
                        ],
                        [
                            'user_id' => $ownerId,
                            'published_at' => now(),
                            'revision_of' => null,
                            'position' => 1,
                        ]
                    );

                    // Set organization default pointer if missing
                    if (empty($organization->default_rate_group_id)) {
                        $organization->default_rate_group_id = $group->id;
                        $organization->save();
                    }

                    // Attach existing rates without a group
                    Rate::where('organization_id', $organization->id)
                        ->whereNull('rate_group_id')
                        ->update(['rate_group_id' => $group->id]);

                    // Attach existing columns without a group
                    Column::where('organization_id', $organization->id)
                        ->whereNull('rate_group_id')
                        ->update(['rate_group_id' => $group->id]);
                });
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * Intentionally left empty; backfilled data is not reversed automatically.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
