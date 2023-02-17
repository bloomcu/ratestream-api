<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call([
            // CategoriesSeeder::class,
            SubscriptionPlansSeeder::class,
            OrganizationsSeeder::class,
            UsersSeeder::class,
            RateGroupsSeeder::class,
            // RatesSeeder::class,
            // SitesSeeder::class,
            // TagsSeeder::class,
            // StatusesSeeder::class,
        ]);
    }
}
