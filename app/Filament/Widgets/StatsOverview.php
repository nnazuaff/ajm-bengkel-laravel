<?php

namespace App\Filament\Widgets;

use App\Models\Sparepart;
use App\Models\Transaction;
use App\Models\WorkOrder;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $totalRevenue = Transaction::where('type', 'income')
            ->whereMonth('transaction_date', now()->month)
            ->sum('amount');

        $activeWorkOrders = WorkOrder::whereIn('status', ['pending', 'in_progress'])->count();

        $completedThisMonth = WorkOrder::where('status', 'completed')
            ->whereMonth('updated_at', now()->month)
            ->count();

        $lowStockItems = Sparepart::whereColumn('stock', '<', 'min_stock')->count();

        return [
            Stat::make(__('Revenue (This Month)'), 'Rp '.number_format($totalRevenue, 0, ',', '.'))
                ->description(__('Income from completed work orders'))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->chart([7, 12, 9, 14, 15, 20, 18]),

            Stat::make(__('Active Work Orders'), $activeWorkOrders)
                ->description(__('Pending & In Progress'))
                ->descriptionIcon('heroicon-m-wrench-screwdriver')
                ->color('warning'),

            Stat::make(__('Completed (This Month)'), $completedThisMonth)
                ->description(__('Work orders finished'))
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make(__('Low Stock Alert'), $lowStockItems)
                ->description(__('Spareparts below minimum'))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($lowStockItems > 0 ? 'danger' : 'success'),
        ];
    }
}
