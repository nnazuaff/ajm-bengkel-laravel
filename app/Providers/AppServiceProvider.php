<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

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
        // Register Audit Observer for all auditable models
        \App\Models\Sparepart::observe(\App\Observers\AuditObserver::class);
        \App\Models\Customer::observe(\App\Observers\AuditObserver::class);
        \App\Models\Vehicle::observe(\App\Observers\AuditObserver::class);
        \App\Models\WorkOrder::observe(\App\Observers\AuditObserver::class);
        \App\Models\WorkOrderItem::observe(\App\Observers\AuditObserver::class);
        \App\Models\Transaction::observe(\App\Observers\AuditObserver::class);
    }
}
