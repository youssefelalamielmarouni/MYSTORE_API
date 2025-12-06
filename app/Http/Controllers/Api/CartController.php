<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CartController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $cart = $user->cart()->with('items.product')->first();

        if (! $cart) {
            return response()->json(['items' => []]);
        }

        return response()->json($cart->load('items.product'));
    }

    public function add(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'sometimes|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $quantity = $data['quantity'] ?? 1;

        $product = Product::findOrFail($data['product_id']);

        if ($product->stock < $quantity) {
            return response()->json(['message' => 'Not enough stock'], 400);
        }

        $cart = $request->user()->cart()->firstOrCreate([]);

        $item = $cart->items()->where('product_id', $product->id)->first();

        if ($item) {
            $item->quantity += $quantity;
            $item->save();
        } else {
            $cart->items()->create([
                'product_id' => $product->id,
                'quantity' => $quantity,
                'price' => $product->price,
            ]);
        }

        return response()->json($cart->load('items.product'));
    }

    public function update(Request $request, CartItem $item)
    {
        $user = $request->user();
        $cart = $user->cart;

        if (! $cart || $item->cart_id !== $cart->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $qty = $request->input('quantity');

        if ($qty === 0) {
            $item->delete();
        } else {
            if ($item->product->stock < $qty) {
                return response()->json(['message' => 'Not enough stock'], 400);
            }
            $item->quantity = $qty;
            $item->save();
        }

        return response()->json($cart->load('items.product'));
    }

    public function remove(Request $request, CartItem $item)
    {
        $user = $request->user();
        $cart = $user->cart;

        if (! $cart || $item->cart_id !== $cart->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $item->delete();

        return response()->json(['message' => 'Removed']);
    }

    public function clear(Request $request)
    {
        $cart = $request->user()->cart;

        if ($cart) {
            $cart->items()->delete();
        }

        return response()->json(['message' => 'Cart cleared']);
    }

    public function checkout(Request $request)
    {
        $cart = $request->user()->cart()->with('items.product')->first();

        if (! $cart || $cart->items->isEmpty()) {
            return response()->json(['message' => 'Cart is empty'], 400);
        }

        $summary = [];

        foreach ($cart->items as $item) {
            $product = $item->product;
            if ($product->stock < $item->quantity) {
                return response()->json(['message' => "Not enough stock for {$product->name}"], 400);
            }
        }

        // Reduce stock and prepare summary
        foreach ($cart->items as $item) {
            $product = $item->product;
            $product->stock -= $item->quantity;
            $product->save();

            $summary[] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'quantity' => $item->quantity,
                'price' => $item->price,
            ];
        }

        // Clear cart
        $cart->items()->delete();

        return response()->json(['message' => 'Checkout successful', 'items' => $summary], 200);
    }
}
