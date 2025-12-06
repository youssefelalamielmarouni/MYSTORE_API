<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cookie;
use App\Services\CartService;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        // send verification email
        try {
            $user->sendEmailVerificationNotification();
        } catch (\Exception $e) {
            // don't break registration on mail failure; log or handle as needed
        }

        $token = $user->createToken('api-token')->plainTextToken;

        // merge guest cart if present in cookie or payload
        $guestItems = [];
        $guestJson = $request->cookie('guest_cart');
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

        if (! empty($guestItems)) {
            $service = new CartService();
            $service->mergeGuestCartIntoUser($user, $guestItems);
            Cookie::queue(Cookie::forget('guest_cart'));
        }

        return response()->json(['user' => $user, 'token' => $token], 201);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'The provided credentials are incorrect.'], 401);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        // merge guest cart if present in cookie or payload
        $guestItems = [];
        $guestJson = $request->cookie('guest_cart');
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

        if (! empty($guestItems)) {
            $service = new CartService();
            $service->mergeGuestCartIntoUser($user, $guestItems);
            Cookie::queue(Cookie::forget('guest_cart'));
        }

        return response()->json(['user' => $user, 'token' => $token], 200);
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user && $request->user()->currentAccessToken()) {
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json(['message' => 'Logged out'], 200);
    }

    public function profile(Request $request)
    {
        return response()->json($request->user());
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . ($user->id ?? 'NULL'),
            'password' => 'sometimes|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        return response()->json($user, 200);
    }
}
