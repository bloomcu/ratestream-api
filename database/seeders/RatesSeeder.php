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
                'group' => 'Unsecured',
                'name' => 'Unsecured - 36',
                'term' => 36,
                'rate' => 8.74,
            ],
            [
                'group' => 'Unsecured',
                'name' => 'Unsecured - 48',
                'term' => 48,
                'rate' => 9.74,
            ],
            [
                'group' => 'Unsecured',
                'name' => 'Unsecured - 60',
                'term' => 60,
                'rate' => 10.74,
            ],

            // New Auto Loan
            [
                'group' => 'New Auto Loan',
                'name' => 'New Auto Loan - 36',
                'year_low' => '2021',
                'year_high' => '2023',
                'term' => 36,
                'rate' => 5.49,
            ],
            [
                'group' => 'New Auto Loan',
                'name' => 'New Auto Loan - 48',
                'year_low' => '2021',
                'year_high' => '2023',
                'term' => 48,
                'rate' => 5.74,
            ],
            [
                'group' => 'New Auto Loan',
                'name' => 'New Auto Loan - 66',
                'year_low' => '2021',
                'year_high' => '2023',
                'term' => 66,
                'rate' => 5.99,
            ],

            // Classic Car
            [
                'group' => 'Classic Car',
                'name' => 'Classic Car - 36',
                'term' => 36,
                'rate' => 8.64,
            ],
            [
                'group' => 'Classic Car',
                'name' => 'Classic Car - 48',
                'term' => 48,
                'rate' => 9.64,
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
