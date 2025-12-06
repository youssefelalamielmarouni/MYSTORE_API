<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    // Add a new card (SIMULATED tokenization - do NOT store real PANs in production)
    public function addCard(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'brand' => 'nullable|string',
            'number' => 'required|string|min:12|max:19',
            'exp_month' => 'required|integer|min:1|max:12',
            'exp_year' => 'required|integer|min:2023',
            'cvc' => 'required|string|min:3|max:4',
            'is_default' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        $user = $request->user();

        // Simulate tokenization and store only metadata
        $last4 = substr($data['number'], -4);
        $token = 'tok_' . Str::random(24);

        if (! empty($data['is_default'])) {
            // unset other defaults
            Card::where('user_id', $user->id)->update(['is_default' => false]);
        }

        $card = Card::create([
            'user_id' => $user->id,
            'brand' => $data['brand'] ?? null,
            'last4' => $last4,
            'exp_month' => $data['exp_month'],
            'exp_year' => $data['exp_year'],
            'token' => $token,
            'is_default' => ! empty($data['is_default']),
        ]);

        return response()->json($card, 201);
    }

    public function listCards(Request $request)
    {
        return response()->json($request->user()->cards()->get());
    }

    public function deleteCard(Request $request, Card $card)
    {
        if ($card->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $card->delete();
        return response()->json(['message' => 'Deleted']);
    }

    public function setDefault(Request $request, Card $card)
    {
        if ($card->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        Card::where('user_id', $request->user()->id)->update(['is_default' => false]);
        $card->is_default = true;
        $card->save();
        return response()->json($card);
    }

    // List orders for the authenticated user
    public function myOrders(Request $request)
    {
        return response()->json($request->user()->orders()->with('items.product')->paginate(20));
    }

    public function showOrder(Request $request, Order $order)
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json($order->load('items.product'));
    }
}
