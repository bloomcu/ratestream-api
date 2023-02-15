<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

// Models
use DDD\Domain\Base\Subscriptions\Plans\Plan;

class SubscriptionPlansSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $plans = [
            [
                'title' => 'Free Plan',
                'price' => 0,
                'interval' => '',
                'buyable' => false,
                'limits' => [
                    'users' => 1,
                    'rates' => 100,
                ],
                'stripe_price_id' => '',
            ],
            [
                'title' => 'Basic - Monthly',
                'price' => 2900,
                'interval' => 'month',
                'buyable' => true,
                'limits' => [
                    'users' => 5,
                    'rates' => 500,
                ],
                'stripe_price_id' => 'price_1MWbZ9EmdmUz6fowjogVHvQg',
            ],
            [
                'title' => 'Basic - Yearly',
                'price' => 29900,
                'interval' => 'year',
                'buyable' => true,
                'limits' => [
                    'users' => 5,
                    'rates' => 500,
                ],
                'stripe_price_id' => 'price_1MWbZ9EmdmUz6fowIDvZWRtz',
            ],
        ];

        foreach ($plans as $plan) {
            Plan::create($plan);
        }
    }
}
