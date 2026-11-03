<?php

namespace App\Providers;

use App\View\Composers\InventoryLayoutComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer([
            'inventory.dashboard.index',
            'inventory.purchase-requests.index',
            'inventory.purchase-requests.approvals',
            'inventory.vendor-prices.index',
            'inventory.purchase-orders.index',
            'inventory.goods-receipts.index',
            'inventory.credit-notes.index',
            'inventory.dispatches.index',
            'inventory.usage.index',
            'inventory.stock.index',
            'inventory.stock-opname.index',
            'inventory.notifications.index',
            'inventory.reports.index',
            'inventory.maintenance.index',
            'inventory.master-data.index',
        ], InventoryLayoutComposer::class);

        RateLimiter::for('login', function (Request $request): Limit {
            $email = Str::lower((string) $request->input('email'));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });
    }
}
