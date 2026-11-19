<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\SubCategory;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'slug' => 'hydrating-cleanser',
                'title' => 'Hydrating Cleanser',
                'category' => 'beauty',
                'sub_category' => 'cleansers',
                'description' => 'A gentle daily cleanser that leaves skin feeling refreshed.',
                'price' => '1900.00',
                'selling_price' => '1900.00',
                'stock' => 4,
            ],
            [
                'slug' => 'storage-basket',
                'title' => 'Storage Basket',
                'category' => 'home-living',
                'sub_category' => 'baskets',
                'description' => 'A woven natural-fiber basket for everyday storage.',
                'price' => '60.00',
                'selling_price' => '40.00',
                'stock' => 4,
            ],
            [
                'slug' => 'classic-scent',
                'title' => 'Classic Scent',
                'category' => 'fragrances',
                'sub_category' => 'perfumes',
                'description' => 'A timeless fragrance available in multiple bottle sizes.',
                'price' => '630.00',
                'selling_price' => '600.00',
                'stock' => 4,
            ],
            [
                'slug' => 'daily-moisturizer',
                'title' => 'Daily Moisturizer',
                'category' => 'beauty',
                'description' => 'A lightweight moisturizer for everyday skincare.',
                'price' => '250.00',
                'selling_price' => '230.00',
                'stock' => 4,
            ],
            [
                'slug' => 'soft-knit-throw',
                'title' => 'Soft Knit Throw',
                'category' => 'home-living',
                'description' => 'A soft knitted throw for a comfortable home.',
                'price' => '450.00',
                'selling_price' => '40.00',
                'stock' => 4,
            ],
            [
                'slug' => 'signature-eau-de-parfum',
                'title' => 'Signature Eau de Parfum',
                'category' => 'fragrances',
                'description' => 'A signature eau de parfum in convenient bottle sizes.',
                'price' => '60.00',
                'selling_price' => '40.00',
                'stock' => 4,
            ],
            [
                'slug' => 'minimal-ceramic-vase',
                'title' => 'Minimal Ceramic Vase',
                'category' => 'home-living',
                'description' => 'A minimal ceramic vase for modern interiors.',
                'price' => '860.00',
                'selling_price' => '850.00',
                'stock' => 4,
            ],
            [
                'slug' => 'everyday-skin-set',
                'title' => 'Everyday Skin Set',
                'category' => 'beauty',
                'description' => 'A curated set of everyday skincare essentials.',
                'price' => '60.00',
                'selling_price' => '60.00',
                'stock' => 4,
            ],
            [
                'slug' => 'sample-classic-t-shirt',
                'title' => 'Sample Classic T-Shirt',
                'category' => 'sample-apparel',
                'description' => 'A sample cotton T-shirt demonstrating size and color variants.',
                'price' => '24.99',
                'selling_price' => '19.99',
                'stock' => 25,
            ],
            [
                'slug' => 'sample-ceramic-mug',
                'title' => 'Sample Ceramic Mug',
                'category' => 'sample-drinkware',
                'description' => 'A sample ceramic mug demonstrating color variants.',
                'price' => '12.00',
                'selling_price' => null,
                'stock' => 27,
            ],
        ];

        foreach ($products as $product) {
            $category = Category::query()
                ->where('slug', $product['category'])
                ->firstOrFail();
            $subCategory = SubCategory::query()
                ->where('slug', $product['sub_category'])
                ->first();
            unset($product['category'], $product['sub_category']);

            Product::query()->updateOrCreate(
                ['slug' => $product['slug']],
                [
                    ...$product,
                    'category_id' => $category->id,
                    'sub_category_id' => $subCategory ? $subCategory->id : null,
                    'is_active' => true,
                ]
            );
        }
    }
}
