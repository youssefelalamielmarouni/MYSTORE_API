<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Product;
use App\Models\Order;

class AdminDashboardController extends Controller
{
    // Simple metrics for admin dashboard
    public function metrics(Request $request)
    {
        $users = User::count();
        $products = Product::count();
        $orders = Order::count();
        $sales = Order::where('payment_status', 'paid')->sum('total');

        return response()->json([
            'users' => $users,
            'products' => $products,
            'orders' => $orders,
            'sales' => $sales,
        ]);
    }

    // Assign role to a user (admins only)
    public function assignRole(Request $request, User $user)
    {
        $request->validate(['role' => 'required|string']);
        $role = $request->input('role');
        $user->assignRole($role);
        return response()->json($user->load('roles'));
    }

    public function revokeRole(Request $request, User $user)
    {
        $request->validate(['role' => 'required|string']);
        $role = $request->input('role');
        $user->removeRole($role);
        return response()->json($user->load('roles'));
    }
}
