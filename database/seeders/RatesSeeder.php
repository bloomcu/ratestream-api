<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

// Models
use DDD\Domain\Rates\Rate;

class RatesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $rates = [
            // Unsecured
            [
                'uid' => 'Unsecured - 36',
                'rate_group_id' => 1,
                'columns' => [
                    'term' => 36,
                    'rate' => 8.74,
                ],
            ],
            [
                'uid' => 'Unsecured - 48',
                'rate_group_id' => 1,
                'columns' => [
                    'term' => 48,
                    'rate' => 9.74,
                ],
            ],
            [
                'uid' => 'Unsecured - 60',
                'rate_group_id' => 1,
                'columns' => [
                    'term' => 60,
                    'rate' => 10.74,
                ],
            ],

            // New Auto Loan
            [
                'uid' => 'New Auto Loan - 36',
                'rate_group_id' => 2,
                'columns' => [
                    'year_low' => '2021',
                    'year_high' => '2023',
                    'term' => 36,
                    'rate' => 5.49,
                ],
            ],
            [
                'uid' => 'New Auto Loan - 48',
                'rate_group_id' => 2,
                'columns' => [
                    'year_low' => '2021',
                    'year_high' => '2023',
                    'term' => 48,
                    'rate' => 5.74,
                ],
            ],
            [
                'uid' => 'New Auto Loan - 66',
                'rate_group_id' => 2,
                'columns' => [
                    'year_low' => '2021',
                    'year_high' => '2023',
                    'term' => 66,
                    'rate' => 5.99,
                ],
            ],

            // Classic Car
            [
                'uid' => 'Classic Car - 36',
                'rate_group_id' => 3,
                'columns' => [
                    'term' => 36,
                    'rate' => 8.64,
                ],
            ],
            [
                'uid' => 'Classic Car - 48',
                'rate_group_id' => 3,
                'columns' => [
                    'term' => 48,
                    'rate' => 9.64,
                ],
            ],
        ];

        foreach ($rates as $rate) {
            Rate::create(array_merge(
                $rate,
                ['organization_id' => 1, 'user_id' => 1]
            ));
        }
    }
}
