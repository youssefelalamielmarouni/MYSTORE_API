<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RoleSeeder extends Seeder
{
    public function run()
    {
        // create roles
        $roles = ['super-admin','admin','manager','user'];
        foreach ($roles as $r) {
            Role::firstOrCreate(['name' => $r]);
        }

        // create some basic permissions (optional)
        Permission::firstOrCreate(['name' => 'manage products']);
        Permission::firstOrCreate(['name' => 'manage orders']);
        Permission::firstOrCreate(['name' => 'manage promotions']);

        // assign all permissions to super-admin role
        $super = Role::where('name', 'super-admin')->first();
        if ($super) {
            $super->givePermissionTo(Permission::all());
        }

        // if a user exists, make the first user super-admin
        $user = User::first();
        if ($user) {
            $user->assignRole('super-admin');
        }
    }
}
