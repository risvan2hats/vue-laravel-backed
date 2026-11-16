<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_a_repeatable_catalog_with_categories_products_and_variants(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('categories', 5);
        $this->assertDatabaseCount('products', 10);
        $this->assertDatabaseCount('product_variants', 21);

        $cleanser = Product::query()->with('category', 'variants')->where('slug', 'hydrating-cleanser')->firstOrFail();
        $this->assertSame('Beauty', $cleanser->category->name);
        $this->assertSame('1900.00', $cleanser->price);
        $this->assertSame(4, $cleanser->variants->sum('stock'));
        $this->assertSame(
            [['size' => '50 ml'], ['size' => '100 ml']],
            $cleanser->variants->pluck('attributes')->all(),
        );

        $shirt = Product::query()->with('variants')->where('slug', 'sample-classic-t-shirt')->firstOrFail();
        $this->assertSame(25, $shirt->variants->sum('stock'));
        $this->assertSame('21.99', $shirt->variants->firstWhere('sku', 'SAMPLE-TSHIRT-WHITE-M')->selling_price);

        $this->assertModelExists(User::query()->where('email', 'test@example.com')->firstOrFail());
        $this->assertModelExists(Category::query()->where('slug', 'home-living')->firstOrFail());
    }
}
