<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

$email = 'verifytest@example.com';

if (! User::where('email', $email)->exists()) {
    $u = User::create([
        'name' => 'Verify Test',
        'email' => $email,
        'password' => Hash::make('secret'),
    ]);

    $u->sendEmailVerificationNotification();
    echo "VERIFICATION SENT\n";
} else {
    $u = User::where('email', $email)->first();
    $u->sendEmailVerificationNotification();
    echo "ALREADY_EXISTS - VERIFICATION SENT\n";
}
