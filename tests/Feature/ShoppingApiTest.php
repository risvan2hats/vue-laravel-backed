<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class ShoppingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_shoppers_can_register_and_read_only_their_own_account(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Taylor Shopper',
            'email' => 'taylor@example.com',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
            'phone' => '555-0100',
            'address' => '10 Market Street',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.email', 'taylor@example.com')
            ->assertJsonPath('data.user.phone', '555-0100')
            ->assertJsonMissingPath('data.user.password')
            ->assertJsonMissingPath('data.user.is_admin');

        $this->assertDatabaseHas('users', [
            'email' => 'taylor@example.com',
            'is_admin' => false,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'taylor@example.com',
            'password' => 'incorrect-password',
        ])->assertUnauthorized();

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'taylor@example.com',
            'password' => 'correct-horse-battery',
        ]);
        $loginResponse->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer');

        $token = $loginResponse->json('data.token');
        Auth::forgetGuards();
        $this->withToken($token)
            ->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('data.email', 'taylor@example.com');

        Auth::forgetGuards();
        $this->withToken($token)
            ->patchJson('/api/v1/user', [
                'phone' => '555-0199',
                'address' => '20 River Road',
                'is_admin' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.phone', '555-0199')
            ->assertJsonPath('data.address', '20 River Road');
        $this->assertDatabaseHas('users', [
            'email' => 'taylor@example.com',
            'phone' => '555-0199',
            'is_admin' => false,
        ]);

        Auth::forgetGuards();
        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertNoContent();
        $this->assertNull(PersonalAccessToken::findToken($token));

        Auth::forgetGuards();
        $this->withToken($token)
            ->getJson('/api/v1/user')
            ->assertUnauthorized();
    }

    public function test_admin_can_create_categories_and_products_with_public_images(): void
    {
        Storage::fake('public');
        $token = $this->adminToken();

        $categoryResponse = $this->withToken($token)->post('/api/v1/categories', [
            'name' => 'Home Goods',
            'description' => 'Useful things for the home.',
            'image' => UploadedFile::fake()->image('home.png'),
        ], ['Accept' => 'application/json']);
        $categoryResponse->assertCreated()
            ->assertJsonPath('data.name', 'Home Goods');
        Storage::disk('public')->assertExists($categoryResponse->json('data.image'));

        $productResponse = $this->withToken($token)->post('/api/v1/products', [
            'category_id' => $categoryResponse->json('data.id'),
            'title' => 'Desk Lamp',
            'description' => 'A compact reading lamp.',
            'price' => '40.00',
            'selling_price' => '32.50',
            'sku' => 'LAMP-001',
            'stock' => 4,
            'image' => UploadedFile::fake()->image('lamp.png'),
        ], ['Accept' => 'application/json']);
        $productResponse->assertCreated()
            ->assertJsonPath('data.title', 'Desk Lamp')
            ->assertJsonPath('data.current_price', '32.50')
            ->assertJsonPath('data.category.name', 'Home Goods');

        Storage::disk('public')->assertExists($productResponse->json('data.image'));
        $this->assertDatabaseHas('products', [
            'title' => 'Desk Lamp',
            'slug' => 'desk-lamp',
            'price' => '40.00',
            'selling_price' => '32.50',
        ]);
    }

    public function test_products_without_an_uploaded_image_use_the_product_placeholder(): void
    {
        $token = $this->adminToken();
        $categoryId = $this->withToken($token)
            ->postJson('/api/v1/categories', ['name' => 'Accessories'])
            ->assertCreated()
            ->json('data.id');
        $productId = $this->withToken($token)
            ->postJson('/api/v1/products', [
                'category_id' => $categoryId,
                'title' => 'Canvas Tote',
                'price' => '18.00',
                'stock' => 10,
            ])
            ->assertCreated()
            ->json('data.id');

        $this->getJson("/api/v1/products/{$productId}")
            ->assertOk()
            ->assertJsonPath('data.image', null)
            ->assertJsonPath('data.image_url', asset('images/product-placeholder.png'));
    }

    public function test_only_admins_can_create_catalog_records(): void
    {
        $this->postJson('/api/v1/categories', ['name' => 'Guest Category'])
            ->assertUnauthorized();

        $shopper = User::factory()->create();
        $token = $shopper->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/categories', ['name' => 'Unauthorized Category'])
            ->assertForbidden();

        $this->assertDatabaseMissing('categories', ['name' => 'Unauthorized Category']);
    }

    public function test_selling_price_cannot_exceed_regular_price(): void
    {
        $token = $this->adminToken();
        $categoryId = $this->withToken($token)
            ->postJson('/api/v1/categories', ['name' => 'Accessories'])
            ->assertCreated()
            ->json('data.id');

        $this->withToken($token)
            ->postJson('/api/v1/products', [
                'category_id' => $categoryId,
                'title' => 'Overpriced Case',
                'price' => '20.00',
                'selling_price' => '21.00',
                'stock' => 5,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['selling_price']);

        $this->assertDatabaseMissing('products', ['title' => 'Overpriced Case']);
    }

    public function test_admin_can_read_update_and_delete_catalog_records(): void
    {
        $token = $this->adminToken();
        $categoryId = $this->withToken($token)
            ->postJson('/api/v1/categories', ['name' => 'Lighting'])
            ->assertCreated()
            ->json('data.id');
        $this->withToken($token)
            ->patchJson("/api/v1/categories/{$categoryId}", ['name' => 'Updated Lighting'])
            ->assertOk()
            ->assertJsonPath('data.slug', 'updated-lighting');
        $productId = $this->withToken($token)
            ->postJson('/api/v1/products', [
                'category_id' => $categoryId,
                'title' => 'Floor Lamp',
                'price' => '80.00',
                'stock' => 2,
            ])
            ->assertCreated()
            ->json('data.id');

        $this->getJson("/api/v1/products/{$productId}")
            ->assertOk()
            ->assertJsonPath('data.title', 'Floor Lamp');

        $this->withToken($token)
            ->patchJson("/api/v1/products/{$productId}", ['title' => 'Updated Floor Lamp'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated Floor Lamp')
            ->assertJsonPath('data.slug', 'updated-floor-lamp');

        $this->withToken($token)
            ->deleteJson("/api/v1/products/{$productId}")
            ->assertNoContent();
        $this->assertDatabaseMissing('products', ['id' => $productId]);

        $this->withToken($token)
            ->deleteJson("/api/v1/categories/{$categoryId}")
            ->assertNoContent();
        $this->assertDatabaseMissing('categories', ['id' => $categoryId]);
    }

    public function test_admin_can_manage_product_variants_and_public_catalog_includes_active_variants(): void
    {
        $adminToken = $this->adminToken();
        $productId = $this->withToken($adminToken)
            ->postJson('/api/v1/products', [
                'category_id' => $this->withToken($adminToken)
                    ->postJson('/api/v1/categories', ['name' => 'Apparel'])
                    ->assertCreated()
                    ->json('data.id'),
                'title' => 'Cotton Shirt',
                'price' => '30.00',
                'stock' => 0,
            ])
            ->assertCreated()
            ->json('data.id');

        $variantResponse = $this->withToken($adminToken)
            ->postJson("/api/v1/products/{$productId}/variants", [
                'attributes' => ['color' => 'Navy', 'size' => 'M'],
                'sku' => 'SHIRT-NAVY-M',
                'price' => '32.00',
                'selling_price' => '28.00',
                'stock' => 4,
            ]);

        $variantResponse->assertCreated()
            ->assertJsonPath('data.attributes.color', 'Navy')
            ->assertJsonPath('data.attributes.size', 'M')
            ->assertJsonPath('data.stock', 4);
        $variantId = $variantResponse->json('data.id');

        $this->assertDatabaseHas('product_variants', [
            'id' => $variantId,
            'product_id' => $productId,
            'sku' => 'SHIRT-NAVY-M',
            'stock' => 4,
        ]);

        $this->getJson("/api/v1/products/{$productId}")
            ->assertOk()
            ->assertJsonPath('data.stock', 4)
            ->assertJsonPath('data.current_price', '28.00')
            ->assertJsonPath('data.variants.0.id', $variantId);

        $this->withToken($adminToken)
            ->postJson("/api/v1/products/{$productId}/variants", [
                'attributes' => ['size' => 'M', 'color' => 'Navy'],
                'stock' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['attributes']);

        $this->withToken($adminToken)
            ->patchJson("/api/v1/products/{$productId}/variants/{$variantId}", [
                'stock' => 6,
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.stock', 6)
            ->assertJsonPath('data.is_active', false);

        $shopperToken = User::factory()->create()->createToken('shopper')->plainTextToken;
        Auth::forgetGuards();
        $this->withToken($shopperToken)
            ->deleteJson("/api/v1/products/{$productId}/variants/{$variantId}")
            ->assertForbidden();

        Auth::forgetGuards();
        $this->withToken($adminToken)
            ->deleteJson("/api/v1/products/{$productId}/variants/{$variantId}")
            ->assertNoContent();
        $this->assertDatabaseMissing('product_variants', ['id' => $variantId]);
    }

    public function test_shoppers_must_select_an_active_product_variant_and_variant_stock_and_price_apply(): void
    {
        $adminToken = $this->adminToken();
        $categoryId = $this->withToken($adminToken)
            ->postJson('/api/v1/categories', ['name' => 'Footwear'])
            ->assertCreated()
            ->json('data.id');
        $productId = $this->withToken($adminToken)
            ->postJson('/api/v1/products', [
                'category_id' => $categoryId,
                'title' => 'Canvas Sneaker',
                'price' => '50.00',
                'selling_price' => '40.00',
                'stock' => 10,
            ])
            ->assertCreated()
            ->json('data.id');
        $redVariantId = $this->withToken($adminToken)
            ->postJson("/api/v1/products/{$productId}/variants", [
                'attributes' => ['color' => 'Red', 'size' => '9'],
                'price' => '60.00',
                'selling_price' => '55.00',
                'stock' => 2,
            ])
            ->assertCreated()
            ->json('data.id');
        $blueVariantId = $this->withToken($adminToken)
            ->postJson("/api/v1/products/{$productId}/variants", [
                'attributes' => ['color' => 'Blue', 'size' => '9'],
                'stock' => 5,
            ])
            ->assertCreated()
            ->json('data.id');

        $this->getJson('/api/v1/products?min_price=55&max_price=55&in_stock=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $productId)
            ->assertJsonPath('data.0.stock', 7);

        $shopperToken = User::factory()->create()->createToken('shopper')->plainTextToken;
        Auth::forgetGuards();
        $this->withToken($shopperToken)
            ->postJson('/api/v1/cart/items', [
                'product_id' => $productId,
                'quantity' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['variant_id']);

        Auth::forgetGuards();
        $redCartResponse = $this->withToken($shopperToken)
            ->postJson('/api/v1/cart/items', [
                'product_id' => $productId,
                'variant_id' => $redVariantId,
                'quantity' => 2,
            ]);
        $redCartResponse->assertCreated()
            ->assertJsonPath('data.total', '110.00')
            ->assertJsonPath('data.items.0.unit_price', '55.00')
            ->assertJsonPath('data.items.0.variant_id', $redVariantId);
        $redCartItemId = $redCartResponse->json('data.items.0.id');

        Auth::forgetGuards();
        $this->withToken($shopperToken)
            ->postJson('/api/v1/cart/items', [
                'product_id' => $productId,
                'variant_id' => $redVariantId,
                'quantity' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);

        Auth::forgetGuards();
        $blueCartResponse = $this->withToken($shopperToken)
            ->postJson('/api/v1/cart/items', [
                'product_id' => $productId,
                'variant_id' => $blueVariantId,
                'quantity' => 1,
            ]);
        $blueCartResponse->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.total', '150.00')
            ->assertJsonPath('data.items.1.unit_price', '40.00')
            ->assertJsonPath('data.items.1.variant.attributes.color', 'Blue');
        $blueCartItemId = $blueCartResponse->json('data.items.1.id');

        Auth::forgetGuards();
        $this->withToken($shopperToken)
            ->patchJson("/api/v1/cart/items/{$blueCartItemId}", ['quantity' => 2])
            ->assertOk()
            ->assertJsonPath('data.total', '190.00');

        Auth::forgetGuards();
        $this->withToken($shopperToken)
            ->patchJson("/api/v1/cart/items/{$blueCartItemId}", ['quantity' => 6])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);

        $this->assertDatabaseHas('cart_items', [
            'id' => $redCartItemId,
            'product_id' => $productId,
            'variant_id' => $redVariantId,
            'quantity' => 2,
            'unit_price' => '55.00',
        ]);
    }

    public function test_product_listing_supports_store_filters_and_sort_options(): void
    {
        $token = $this->adminToken();
        $lightingCategory = $this->withToken($token)
            ->postJson('/api/v1/categories', ['name' => 'Lighting'])
            ->assertCreated()
            ->json('data');
        $homeCategory = $this->withToken($token)
            ->postJson('/api/v1/categories', ['name' => 'Home Goods'])
            ->assertCreated()
            ->json('data');

        $createProduct = fn (array $attributes): array => $this->withToken($token)
            ->postJson('/api/v1/products', $attributes)
            ->assertCreated()
            ->json('data');

        $tableLamp = $createProduct([
            'category_id' => $lightingCategory['id'],
            'title' => 'Table Lamp',
            'price' => '60.00',
            'selling_price' => '45.00',
            'stock' => 3,
        ]);
        $wallLamp = $createProduct([
            'category_id' => $lightingCategory['id'],
            'title' => 'Wall Lamp',
            'price' => '100.00',
            'selling_price' => '50.00',
            'stock' => 0,
        ]);
        $ceramicBowl = $createProduct([
            'category_id' => $homeCategory['id'],
            'title' => 'Ceramic Bowl',
            'price' => '40.00',
            'stock' => 5,
        ]);

        DB::table('products')->where('id', $tableLamp['id'])->update(['created_at' => '2026-01-01 10:00:00']);
        DB::table('products')->where('id', $wallLamp['id'])->update(['created_at' => '2026-01-01 11:00:00']);
        DB::table('products')->where('id', $ceramicBowl['id'])->update(['created_at' => '2026-01-01 12:00:00']);

        $this->getJson('/api/v1/products?search=Lamp&category=lighting&min_price=45&max_price=45&in_stock=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Table Lamp')
            ->assertJsonPath('data.0.current_price', '45.00');

        $this->getJson('/api/v1/products?category_id='.$lightingCategory['id'])
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/v1/products?sort=featured')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Ceramic Bowl');

        $this->getJson('/api/v1/products?sort=price-asc')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Ceramic Bowl');

        $this->getJson('/api/v1/products?sort=price-desc')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Wall Lamp');

        $this->getJson('/api/v1/products?sort=discount')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Wall Lamp')
            ->assertJsonPath('data.1.title', 'Table Lamp')
            ->assertJsonPath('data.2.title', 'Ceramic Bowl');
    }

    public function test_product_listing_rejects_rating_sort_until_ratings_exist(): void
    {
        $this->getJson('/api/v1/products?sort=rating')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort']);
    }

    public function test_cart_items_are_private_and_totals_use_the_selling_price(): void
    {
        $adminToken = $this->adminToken();
        $categoryId = $this->withToken($adminToken)
            ->postJson('/api/v1/categories', ['name' => 'Kitchen'])
            ->assertCreated()
            ->json('data.id');
        $productId = $this->withToken($adminToken)
            ->postJson('/api/v1/products', [
                'category_id' => $categoryId,
                'title' => 'Mixing Bowl',
                'price' => '15.00',
                'selling_price' => '12.50',
                'stock' => 3,
            ])
            ->assertCreated()
            ->json('data.id');

        $shopper = User::factory()->create();
        $shopperToken = $shopper->createToken('shopper')->plainTextToken;
        Auth::forgetGuards();
        $cartResponse = $this->withToken($shopperToken)
            ->postJson('/api/v1/cart/items', [
                'product_id' => $productId,
                'quantity' => 2,
            ]);

        $cartResponse->assertCreated()
            ->assertJsonPath('data.item_count', 2)
            ->assertJsonPath('data.total', '25.00')
            ->assertJsonPath('data.items.0.unit_price', '12.50');
        $itemId = $cartResponse->json('data.items.0.id');

        Auth::forgetGuards();
        $this->withToken($shopperToken)
            ->patchJson("/api/v1/cart/items/{$itemId}", ['quantity' => 3])
            ->assertOk()
            ->assertJsonPath('data.total', '37.50');

        $otherShopper = User::factory()->create();
        $otherToken = $otherShopper->createToken('other-shopper')->plainTextToken;
        Auth::forgetGuards();
        $this->withToken($otherToken)
            ->patchJson("/api/v1/cart/items/{$itemId}", ['quantity' => 1])
            ->assertNotFound();

        Auth::forgetGuards();
        $this->withToken($shopperToken)
            ->patchJson("/api/v1/cart/items/{$itemId}", ['quantity' => 4])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);

        $this->assertDatabaseHas('cart_items', [
            'id' => $itemId,
            'quantity' => 3,
        ]);

        Auth::forgetGuards();
        $this->withToken($shopperToken)
            ->deleteJson("/api/v1/cart/items/{$itemId}")
            ->assertOk()
            ->assertJsonPath('data.item_count', 0)
            ->assertJsonPath('data.total', '0.00');
        $this->assertDatabaseMissing('cart_items', ['id' => $itemId]);
    }

    public function test_admin_command_can_grant_catalog_access_to_an_existing_account(): void
    {
        $user = User::factory()->create();

        $this->artisan('app:make-admin', ['email' => $user->email])
            ->assertExitCode(0);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_admin' => true,
        ]);
    }

    public function test_api_cors_allows_the_configured_vue_development_origin(): void
    {
        $this->options('/api/v1/products', [], [
            'Origin' => 'http://localhost:5173',
            'Access-Control-Request-Method' => 'GET',
        ])
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
    }

    private function adminToken(): string
    {
        return User::factory()
            ->create(['is_admin' => true])
            ->createToken('admin')
            ->plainTextToken;
    }
}
