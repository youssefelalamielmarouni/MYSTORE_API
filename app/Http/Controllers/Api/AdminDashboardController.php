<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Product;
use App\Models\Order;
use App\Models\PageView;
use Carbon\Carbon;

class AdminDashboardController extends Controller
{
    // Comprehensive metrics for admin dashboard
    public function metrics(Request $request)
    {
        // Basic counts
        $totalUsers = User::count();
        $totalAdmins = User::whereHas('roles', function ($q) {
            $q->where('name', 'admin');
        })->count();
        $totalProducts = Product::count();
        $totalOrders = Order::count();

        // Sales statistics
        $totalRevenue = Order::where('payment_status', 'paid')->sum('total');
        $totalSales = Order::where('payment_status', 'paid')->count();
        
        // Order status breakdown
        $ordersCompleted = Order::where('status', 'completed')->count();
        $ordersPending = Order::where('status', 'pending')->count();
        $ordersCancelled = Order::where('status', 'cancelled')->count();
        
        // Payment status breakdown
        $paidOrders = Order::where('payment_status', 'paid')->count();
        $unpaidOrders = Order::where('payment_status', 'unpaid')->count();

        // Product stock info
        $lowStockProducts = Product::where('stock', '<', 10)->count();
        $outOfStockProducts = Product::where('stock', 0)->count();
        
        // Recent activity
        $recentOrders = Order::latest()->take(5)->count();

        return response()->json([
            'summary' => [
                'total_users' => $totalUsers,
                'total_admins' => $totalAdmins,
                'total_products' => $totalProducts,
                'total_orders' => $totalOrders,
            ],
            'sales' => [
                'total_revenue' => $totalRevenue,
                'total_sales' => $totalSales,
                'average_order_value' => $totalSales > 0 ? round($totalRevenue / $totalSales, 2) : 0,
            ],
            'orders' => [
                'completed' => $ordersCompleted,
                'pending' => $ordersPending,
                'cancelled' => $ordersCancelled,
                'paid' => $paidOrders,
                'unpaid' => $unpaidOrders,
            ],
            'inventory' => [
                'low_stock_count' => $lowStockProducts,
                'out_of_stock_count' => $outOfStockProducts,
            ],
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

    // Get visitor analytics
    public function visitorAnalytics(Request $request)
    {
        $days = $request->query('days', 30); // default 30 days

        $startDate = Carbon::now()->subDays($days);

        // Total unique visitors (unique IP addresses)
        $uniqueVisitors = PageView::where('created_at', '>=', $startDate)
            ->distinct('ip_address')
            ->count('ip_address');

        // Authenticated visitors
        $authenticatedVisitors = PageView::where('created_at', '>=', $startDate)
            ->whereNotNull('user_id')
            ->distinct('user_id')
            ->count('user_id');

        // Guest visitors
        $guestVisitors = $uniqueVisitors - $authenticatedVisitors;

        // Daily visitor trend
        $dailyVisitors = PageView::where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, COUNT(DISTINCT ip_address) as visitors')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Most visited pages
        $topPages = PageView::where('created_at', '>=', $startDate)
            ->selectRaw('page_url, COUNT(*) as views')
            ->groupBy('page_url')
            ->orderByDesc('views')
            ->take(10)
            ->get();

        // Page views by date
        $pageViewsPerDay = PageView::where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as views')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'period_days' => $days,
            'summary' => [
                'unique_visitors' => $uniqueVisitors,
                'authenticated_visitors' => $authenticatedVisitors,
                'guest_visitors' => $guestVisitors,
                'total_page_views' => PageView::where('created_at', '>=', $startDate)->count(),
            ],
            'daily_visitors' => $dailyVisitors,
            'page_views_per_day' => $pageViewsPerDay,
            'top_pages' => $topPages,
        ]);
    }
}
