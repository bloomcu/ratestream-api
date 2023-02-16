<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

// Models
use DDD\Domain\Rates\RateGroup;

class RateGroupsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $groups = [
            ['title' => 'Unsecured'],
            ['title' => 'New Auto Loan'],
            ['title' => 'Classic Car'],
        ];

        foreach ($groups as $group) {
            RateGroup::create(array_merge(
                $group,
                ['organization_id' => 1, 'user_id' => 1]
            ));
        }
    }
}
