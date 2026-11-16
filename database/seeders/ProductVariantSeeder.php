<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductVariantSeeder extends Seeder
{
    public function run(): void
    {
        $variantsByProduct = [
            'hydrating-cleanser' => [
                $this->variant(['size' => '50 ml'], 2),
                $this->variant(['size' => '100 ml'], 2),
            ],
            'storage-basket' => [
                $this->variant(['size' => 'Small', 'color' => 'Natural'], 2),
                $this->variant(['size' => 'Large', 'color' => 'Natural'], 2),
            ],
            'classic-scent' => [
                $this->variant(['volume' => '30 ml'], 2),
                $this->variant(['volume' => '50 ml'], 2),
            ],
            'daily-moisturizer' => [
                $this->variant(['size' => '50 ml'], 2),
                $this->variant(['size' => '100 ml'], 2),
            ],
            'soft-knit-throw' => [
                $this->variant(['size' => '50 x 60 in'], 2),
                $this->variant(['size' => '60 x 80 in'], 2),
            ],
            'signature-eau-de-parfum' => [
                $this->variant(['volume' => '30 ml'], 2),
                $this->variant(['volume' => '50 ml'], 2),
            ],
            'minimal-ceramic-vase' => [
                $this->variant(['height' => '20 cm'], 2),
                $this->variant(['height' => '30 cm'], 2),
            ],
            'everyday-skin-set' => [
                $this->variant(['set' => 'Essential'], 2),
                $this->variant(['set' => 'Complete'], 2),
            ],
            'sample-classic-t-shirt' => [
                $this->variant(['color' => 'Black', 'size' => 'S'], 8, 'SAMPLE-TSHIRT-BLACK-S'),
                $this->variant(['color' => 'Black', 'size' => 'M'], 12, 'SAMPLE-TSHIRT-BLACK-M'),
                $this->variant(
                    ['color' => 'White', 'size' => 'M'],
                    5,
                    'SAMPLE-TSHIRT-WHITE-M',
                    '26.99',
                    '21.99',
                ),
            ],
            'sample-ceramic-mug' => [
                $this->variant(['color' => 'White'], 20, 'SAMPLE-MUG-WHITE'),
                $this->variant(['color' => 'Sage'], 7, 'SAMPLE-MUG-SAGE', '13.50'),
            ],
        ];

        foreach ($variantsByProduct as $productSlug => $variants) {
            $product = Product::query()->where('slug', $productSlug)->firstOrFail();
            $this->syncLegacyStandardVariant($product, $variants[0]);

            foreach ($variants as $variant) {
                $this->createVariant($product, $variant);
            }
        }
    }

    /**
     * @return array{attributes: array<string, string>, sku: string, price: ?string, selling_price: ?string, stock: int}
     */
    private function variant(
        array $attributes,
        int $stock,
        ?string $sku = null,
        ?string $price = null,
        ?string $sellingPrice = null,
    ): array {
        return [
            'attributes' => $attributes,
            'sku' => $sku ?? 'DEMO-'.Str::upper(Str::slug(implode('-', $attributes))),
            'price' => $price,
            'selling_price' => $sellingPrice,
            'stock' => $stock,
        ];
    }

    /**
     * @param  array{attributes: array<string, string>, sku: string, price: ?string, selling_price: ?string, stock: int}  $variant
     */
    private function createVariant(Product $product, array $variant): void
    {
        $sku = str_starts_with($variant['sku'], 'DEMO-')
            ? 'DEMO-'.$product->id.'-'.substr($variant['sku'], strlen('DEMO-'))
            : $variant['sku'];

        $product->variants()->updateOrCreate(
            ['options_hash' => $this->optionsHash($variant['attributes'])],
            [
                'attributes' => $variant['attributes'],
                'sku' => $sku,
                'price' => $variant['price'],
                'selling_price' => $variant['selling_price'],
                'stock' => $variant['stock'],
                'is_active' => true,
            ],
        );
    }

    /**
     * @param  array{attributes: array<string, string>, sku: string, price: ?string, selling_price: ?string, stock: int}  $variant
     */
    private function syncLegacyStandardVariant(Product $product, array $variant): void
    {
        if ($product->variants()->where('options_hash', $this->optionsHash($variant['attributes']))->exists()) {
            return;
        }

        $legacy = $product->variants()
            ->where('sku', 'DEMO-'.$product->id.'-STANDARD')
            ->first();

        if ($legacy instanceof ProductVariant && $legacy->attributes === ['option' => 'Standard']) {
            $legacy->update([
                'attributes' => $variant['attributes'],
                'options_hash' => $this->optionsHash($variant['attributes']),
                'sku' => str_starts_with($variant['sku'], 'DEMO-')
                    ? 'DEMO-'.$product->id.'-'.substr($variant['sku'], strlen('DEMO-'))
                    : $variant['sku'],
                'price' => $variant['price'],
                'selling_price' => $variant['selling_price'],
                'stock' => $variant['stock'],
                'is_active' => true,
            ]);
        }
    }

    /**
     * @param  array<string, string>  $attributes
     */
    private function optionsHash(array $attributes): string
    {
        ksort($attributes);

        return hash('sha256', json_encode($attributes, JSON_THROW_ON_ERROR));
    }
}
