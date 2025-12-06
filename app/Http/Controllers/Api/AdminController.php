<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class AdminController extends Controller
{
    // List all users with admin flag (paginated)
    public function index()
    {
        return response()->json(User::paginate(20));
    }

    // List admins only
    public function admins()
    {
        return response()->json(User::where('is_admin', true)->paginate(20));
    }

    // Create a new admin user (admins only)
    public function createAdmin(Request $request)
    {
        $this->authorizeAdmin($request);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
            'is_admin' => true,
        ]);

        return response()->json($user, 201);
    }

    // Create a promotion code (admins only)
    public function createPromotion(Request $request)
    {
        $this->authorizeAdmin($request);

        $validator = Validator::make($request->all(), [
            'code' => 'required|string|unique:promotions,code',
            'type' => 'required|in:percent,fixed',
            'value' => 'required|numeric|min:0',
            'duration_days' => 'sometimes|integer|min:1',
            'starts_at' => 'sometimes|date',
            'ends_at' => 'sometimes|date|after:starts_at',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        // if duration_days provided, calculate starts_at and ends_at
        $starts = isset($data['starts_at']) ? Carbon::parse($data['starts_at']) : Carbon::now();
        $ends = null;
        if (isset($data['duration_days'])) {
            $ends = (clone $starts)->addDays($data['duration_days']);
        } elseif (isset($data['ends_at'])) {
            $ends = Carbon::parse($data['ends_at']);
        }

        $promo = Promotion::create([
            'code' => $data['code'],
            'type' => $data['type'],
            'value' => $data['value'],
            'starts_at' => $starts,
            'ends_at' => $ends,
            'active' => true,
        ]);

        return response()->json($promo, 201);
    }

    public function listPromotions()
    {
        return response()->json(Promotion::orderBy('created_at','desc')->paginate(20));
    }

    public function deletePromotion(Request $request, Promotion $promotion)
    {
        $this->authorizeAdmin($request);
        $promotion->delete();
        return response()->json(['message' => 'Deleted'], 200);
    }

    protected function authorizeAdmin(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->is_admin) {
            abort(response()->json(['message' => 'Forbidden. Admins only.'], 403));
        }
    }

    // Promote a user to admin
    public function promote(Request $request, User $user)
    {
        $user->is_admin = true;
        $user->save();
        return response()->json($user, 200);
    }

    // Demote an admin to regular user
    public function demote(Request $request, User $user)
    {
        // prevent demoting yourself accidentally
        if ($request->user()->id === $user->id) {
            return response()->json(['message' => 'You cannot demote yourself'], 403);
        }

        $user->is_admin = false;
        $user->save();
        return response()->json($user, 200);
    }

    // Delete a user
    public function destroy(Request $request, User $user)
    {
        // prevent deleting yourself
        if ($request->user()->id === $user->id) {
            return response()->json(['message' => 'You cannot delete yourself'], 403);
        }

        $user->delete();
        return response()->json(['message' => 'Deleted'], 200);
    }
}
