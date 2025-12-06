<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Validator;

class GuestCartController extends Controller
{
    protected $cookieName = 'guest_cart';

    protected function readCart(Request $request)
    {
        $json = $request->cookie($this->cookieName);
        if (! $json) {
            return [];
        }

        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    protected function writeCart($data)
    {
        $json = json_encode($data);
        // set cookie for 7 days
        return Cookie::queue(Cookie::make($this->cookieName, $json, 60 * 24 * 7));
    }

    protected function clearCartCookie()
    {
        return Cookie::queue(Cookie::forget($this->cookieName));
    }

    public function index(Request $request)
    {
        $items = $this->readCart($request);
        return response()->json(['items' => $items]);
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
        $items = $this->readCart($request);

        // find existing
        $found = false;
        foreach ($items as &$it) {
            if ($it['product_id'] == $data['product_id']) {
                $it['quantity'] = ($it['quantity'] ?? 0) + ($data['quantity'] ?? 1);
                $found = true;
                break;
            }
        }
        if (! $found) {
            $items[] = ['product_id' => $data['product_id'], 'quantity' => ($data['quantity'] ?? 1)];
        }

        $this->writeCart($items);

        return response()->json(['items' => $items]);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'required|integer|min:0',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $items = $this->readCart($request);

        foreach ($items as $i => $it) {
            if ($it['product_id'] == $data['product_id']) {
                if ($data['quantity'] <= 0) {
                    array_splice($items, $i, 1);
                } else {
                    $items[$i]['quantity'] = $data['quantity'];
                }
                break;
            }
        }

        $this->writeCart($items);
        return response()->json(['items' => $items]);
    }

    public function remove(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|integer|exists:products,id',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $items = $this->readCart($request);
        $items = array_values(array_filter($items, function ($it) use ($data) {
            return $it['product_id'] != $data['product_id'];
        }));

        $this->writeCart($items);
        return response()->json(['items' => $items]);
    }

    public function clear(Request $request)
    {
        $this->clearCartCookie();
        return response()->json(['message' => 'Cart cleared']);
    }
}
