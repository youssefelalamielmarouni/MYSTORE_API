<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Cart;
use App\Models\Product;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cookie;
use App\Services\CartService;

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
        $user = $request->user();
        $cart = $user->cart()->with('items.product')->first();

        if (! $cart || $cart->items->isEmpty()) {
            return response()->json(['message' => 'Cart is empty'], 400);
        }

        // Validate payment input
        $validator = Validator::make($request->all(), [
            'payment_method' => 'required|in:card,cod',
            'card_id' => 'required_if:payment_method,card|nullable|integer|exists:cards,id',
            'promo_code' => 'sometimes|string|exists:promotions,code',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check stock availability
        foreach ($cart->items as $item) {
            $product = $item->product;
            if ($product->stock < $item->quantity) {
                return response()->json(['message' => "Not enough stock for {$product->name}"], 400);
            }
        }

        // Build order and calculate total inside a transaction
            DB::beginTransaction();
        try {
            $total = 0;
            foreach ($cart->items as $item) {
                $total += ($item->price * $item->quantity);
            }

            $order = Order::create([
                'user_id' => $user->id,
                'total' => $total,
                'status' => 'pending',
                'payment_method' => $request->input('payment_method', 'cod'),
                'payment_status' => 'pending',
                'card_id' => $request->input('card_id'),
            ]);

            // Apply promotion if present
            if ($request->filled('promo_code')) {
                $promo = \App\Models\Promotion::where('code', $request->input('promo_code'))->first();
                if ($promo && $promo->isActive()) {
                    if ($promo->type === 'percent') {
                        $discount = ($promo->value / 100) * $total;
                    } else {
                        $discount = $promo->value;
                    }
                    $total = max(0, $total - $discount);
                    // update order total
                    $order->total = $total;
                    $order->save();
                }
            }

            foreach ($cart->items as $item) {
                $order->items()->create([
                    'product_id' => $item->product->id,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                ]);

                // reduce stock
                $product = $item->product;
                $product->stock -= $item->quantity;
                $product->save();
            }

            // simulate payment if card
            if ($order->payment_method === 'card') {
                // For simulation: we accept the charge always and mark paid.
                $order->payment_status = 'paid';
                $order->status = 'paid';
                $order->save();
            }

            // clear cart
            $cart->items()->delete();

            DB::commit();

            return response()->json(['message' => 'Order created', 'order' => $order->load('items')], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Checkout failed', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Merge guest cart (from cookie or payload) into authenticated user's cart.
     */
    public function mergeGuest(Request $request, CartService $service)
    {
        $user = $request->user();

        // attempt to read guest cart from cookie or request body
        $guestJson = $request->cookie('guest_cart');
        $guestItems = [];
        if ($guestJson) {
            $data = json_decode($guestJson, true);
            if (is_array($data)) {
                $guestItems = $data;
            }
        } elseif ($request->has('guest_cart')) {
            $data = $request->input('guest_cart');
            if (is_array($data)) {
                $guestItems = $data;
            }
        }

        if (empty($guestItems)) {
            return response()->json(['message' => 'No guest cart to merge'], 400);
        }

        $cart = $service->mergeGuestCartIntoUser($user, $guestItems);

        // clear cookie
        Cookie::queue(Cookie::forget('guest_cart'));

        return response()->json(['message' => 'Merged', 'cart' => $cart]);
    }
}
