<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Fix for older MySQL/MariaDB versions where index key length is limited.
        // Ensures default string columns are created with length 191 so
        // unique indexes on utf8mb4 columns (4 bytes per char) fit within
        // older InnoDB limits.
        Schema::defaultStringLength(191);
    }
}
