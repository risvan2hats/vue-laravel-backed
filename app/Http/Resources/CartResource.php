<?php

namespace App\Http\Resources;

use App\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $items = $this->items;
        $totalCents = $items->sum(
            fn (CartItem $item): int => (int) round((float) $item->unit_price * 100) * $item->quantity,
        );

        return [
            'id' => $this->id,
            'items' => $items->map(fn (CartItem $item): array => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product' => ProductResource::make($item->product),
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'line_total' => number_format(
                    ((int) round((float) $item->unit_price * 100) * $item->quantity) / 100,
                    2,
                    '.',
                    '',
                ),
            ]),
            'item_count' => $items->sum('quantity'),
            'total' => number_format($totalCents / 100, 2, '.', ''),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
