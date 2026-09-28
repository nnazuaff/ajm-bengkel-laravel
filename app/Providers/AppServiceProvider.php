<?php

namespace App\Providers;

use App\Models\Customer;
use App\Models\Sparepart;
use App\Models\Transaction;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use App\Observers\AuditObserver;
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
        Sparepart::observe(AuditObserver::class);
        Customer::observe(AuditObserver::class);
        Vehicle::observe(AuditObserver::class);
        WorkOrder::observe(AuditObserver::class);
        WorkOrderItem::observe(AuditObserver::class);
        Transaction::observe(AuditObserver::class);
    }
}
