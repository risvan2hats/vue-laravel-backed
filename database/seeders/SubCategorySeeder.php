<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\SubCategory;
use Illuminate\Database\Seeder;

class SubCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define some sample sub‑categories per main category slug
        $subCategories = [
            'beauty' => [
                ['name' => 'Cleansers', 'slug' => 'cleansers'],
                ['name' => 'Moisturizers', 'slug' => 'moisturizers'],
                ['name' => 'Serums', 'slug' => 'serums'],
            ],
            'home-living' => [
                ['name' => 'Baskets', 'slug' => 'baskets'],
                ['name' => 'Vases', 'slug' => 'vases'],
                ['name' => 'Throws', 'slug' => 'throws'],
            ],
            'fragrances' => [
                ['name' => 'Perfumes', 'slug' => 'perfumes'],
                ['name' => 'Colognes', 'slug' => 'colognes'],
            ],
            'sample-apparel' => [
                ['name' => 'T‑Shirts', 'slug' => 't-shirts'],
                ['name' => 'Hoodies', 'slug' => 'hoodies'],
            ],
            'sample-drinkware' => [
                ['name' => 'Mugs', 'slug' => 'mugs'],
                ['name' => 'Glasses', 'slug' => 'glasses'],
            ],
        ];

        foreach ($subCategories as $categorySlug => $items) {
            $category = Category::query()->where('slug', $categorySlug)->first();
            if (! $category) {
                continue;
            }

            foreach ($items as $item) {
                SubCategory::query()->updateOrCreate(
                    ['slug' => $item['slug']],
                    [
                        'name' => $item['name'],
                        'category_id' => $category->id,
                    ]
                );
            }
        }
    }
}
