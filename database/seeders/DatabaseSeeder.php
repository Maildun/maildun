<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            InstallSeeder::class,
            AdminUserSeeder::class,
            AudienceSeeder::class,
            SubscriberSeeder::class,
            WorkspaceMembersDemoSeeder::class,
            FullDemoSeeder::class,
        ]);
    }
}
