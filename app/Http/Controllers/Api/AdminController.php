<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
