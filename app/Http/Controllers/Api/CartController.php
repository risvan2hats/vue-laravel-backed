<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CartResource;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private readonly CartService $carts) {}

    public function show(Request $request): CartResource
    {
        /** @var User $user */
        $user = $request->user();

        return new CartResource($this->carts->show($user));
    }

    public function addItem(Request $request): CartResource
    {
        /** @var User $user */
        $user = $request->user();
        $attributes = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        return new CartResource($this->carts->addItem($user, $attributes['product_id'], $attributes['quantity']));
    }

    public function updateItem(Request $request, int $item): CartResource
    {
        /** @var User $user */
        $user = $request->user();
        $attributes = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        return new CartResource($this->carts->updateItem($user, $item, $attributes['quantity']));
    }

    public function removeItem(Request $request, int $item): CartResource
    {
        /** @var User $user */
        $user = $request->user();

        return new CartResource($this->carts->removeItem($user, $item));
    }

    public function clear(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->carts->clear($user);

        return response()->json(status: JsonResponse::HTTP_NO_CONTENT);
    }
}
