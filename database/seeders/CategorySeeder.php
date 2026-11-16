<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => 'Beauty', 'slug' => 'beauty'],
            ['name' => 'Home & Living', 'slug' => 'home-living'],
            ['name' => 'Fragrances', 'slug' => 'fragrances'],
            ['name' => 'Sample Apparel', 'slug' => 'sample-apparel'],
            ['name' => 'Sample Drinkware', 'slug' => 'sample-drinkware'],
        ] as $category) {
            Category::query()->updateOrCreate(
                ['slug' => $category['slug']],
                ['name' => $category['name'], 'is_active' => true],
            );
        }
    }
}
