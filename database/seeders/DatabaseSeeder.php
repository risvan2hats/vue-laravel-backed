<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'risvanas@2hatslogic.com'],
            ['name' => 'Risvan 2Hats', 'password' => 'Risvan5465'],
        );

        $this->call([
            CategorySeeder::class,
            SubCategorySeeder::class,
            ProductSeeder::class,
            ProductVariantSeeder::class,
        ]);
    }
}
