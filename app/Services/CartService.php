<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Support\Facades\Log;

class CartService
{
    /**
     * Merge guest items (array of ['product_id'=>..,'quantity'=>..]) into user's cart.
     * Returns the cart after merge.
     */
    public function mergeGuestCartIntoUser($user, array $guestItems)
    {
        $cart = $user->cart()->firstOrCreate([]);

        foreach ($guestItems as $g) {
            $productId = isset($g['product_id']) ? intval($g['product_id']) : null;
            $quantity = isset($g['quantity']) ? max(0, intval($g['quantity'])) : 1;

            if (! $productId || $quantity <= 0) {
                continue;
            }

            $product = Product::find($productId);
            if (! $product) {
                continue;
            }

            // ensure quantity does not exceed stock
            $quantity = min($quantity, max(0, intval($product->stock)));

            if ($quantity === 0) {
                continue;
            }

            $item = $cart->items()->where('product_id', $productId)->first();
            if ($item) {
                $item->quantity = min($item->quantity + $quantity, $product->stock);
                $item->save();
            } else {
                $cart->items()->create([
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'price' => $product->price,
                ]);
            }
        }

        return $cart->load('items.product');
    }
}
