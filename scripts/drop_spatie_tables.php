<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

DB::statement('SET FOREIGN_KEY_CHECKS=0');
DB::statement('DROP TABLE IF EXISTS role_has_permissions');
DB::statement('DROP TABLE IF EXISTS model_has_roles');
DB::statement('DROP TABLE IF EXISTS model_has_permissions');
DB::statement('DROP TABLE IF EXISTS roles');
DB::statement('DROP TABLE IF EXISTS permissions');
DB::statement('SET FOREIGN_KEY_CHECKS=1');

echo "Dropped spatie tables\n";
