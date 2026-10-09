<?php

use App\Http\Controllers\CheckInQrController;
use App\Http\Controllers\CustomerDocumentationController;
use App\Http\Controllers\CustomerReceiptController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PublicHomeController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\ServiceDocumentationController;
use App\Livewire\AuditLogs;
use App\Livewire\Bookings;
use App\Livewire\CheckIns;
use App\Livewire\CustomerBooking;
use App\Livewire\CustomerPortal;
use App\Livewire\Customers;
use App\Livewire\GuestBooking;
use App\Livewire\Inventory;
use App\Livewire\Mechanics;
use App\Livewire\Payments;
use App\Livewire\PublicCheckIn;
use App\Livewire\ReceiptEditor;
use App\Livewire\Receipts;
use App\Livewire\Reports;
use App\Livewire\ServiceHistory;
use App\Livewire\Services;
use App\Livewire\Vehicles;
use App\Livewire\WorkshopSettings;
use Illuminate\Support\Facades\Route;

Route::get('/', PublicHomeController::class)->name('home');
Route::livewire('booking/guest', GuestBooking::class)->name('booking.guest');
Route::livewire('check-in', PublicCheckIn::class)->name('check-in');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::livewire('check-ins', CheckIns::class)->middleware('can:work-services')->name('check-ins.index');
    Route::get('check-ins/qr', CheckInQrController::class)->middleware('can:work-services')->name('check-ins.qr');

    Route::livewire('portal', CustomerPortal::class)->middleware('can:customer-portal')->name('portal');
    Route::get('portal/receipts/{receipt}', [CustomerReceiptController::class, 'show'])->middleware('can:customer-portal')->name('customer.receipts.show');
    Route::get('portal/receipts/{receipt}/image', [CustomerReceiptController::class, 'image'])->middleware('can:customer-portal')->name('customer.receipts.image');
    Route::get('portal/documentation/{documentation}', [CustomerDocumentationController::class, 'show'])->middleware('can:customer-portal')->name('customer.documentation.show');
    Route::livewire('booking', CustomerBooking::class)->middleware('can:customer-portal')->name('booking.mine');
    Route::livewire('bookings', Bookings::class)->middleware('can:manage-workshop')->name('bookings.index');

    Route::livewire('customers', Customers::class)->middleware('can:manage-workshop')->name('customers.index');
    Route::livewire('vehicles', Vehicles::class)->middleware('can:manage-workshop')->name('vehicles.index');
    Route::livewire('services', Services::class)->middleware('can:work-services')->name('services.index');
    Route::livewire('services/{serviceOrder}', Services::class)->middleware('can:work-services')->name('services.detail');
    Route::livewire('inventory', Inventory::class)->middleware('can:manage-workshop')->name('inventory.index');
    Route::livewire('receipts', Receipts::class)->middleware('can:manage-workshop')->name('receipts.index');
    Route::livewire('receipts/create', ReceiptEditor::class)->middleware('can:manage-workshop')->name('receipts.create');
    Route::livewire('receipts/{receipt}/edit', ReceiptEditor::class)->middleware('can:manage-workshop')->name('receipts.edit');
    Route::livewire('payments', Payments::class)->middleware('can:manage-workshop')->name('payments.index');
    Route::get('receipts/{receipt}/view', [ReceiptController::class, 'show'])->middleware('can:manage-workshop')->name('receipts.show');
    Route::get('receipts/{receipt}/image', [ReceiptController::class, 'image'])->middleware('can:manage-workshop')->name('receipts.image');
    Route::get('documentation/{documentation}', [ServiceDocumentationController::class, 'show'])->middleware('can:work-services')->name('documentation.show');
    Route::livewire('history', ServiceHistory::class)->middleware('can:manage-workshop')->name('history.index');
    Route::livewire('mechanics', Mechanics::class)->middleware('can:manage-workshop')->name('mechanics.index');
    Route::livewire('workshop-settings', WorkshopSettings::class)->middleware('can:manage-users')->name('workshop-settings.edit');
    Route::livewire('audit-log', AuditLogs::class)->middleware('can:manage-workshop')->name('audit.index');
    Route::livewire('reports', Reports::class)->middleware('can:manage-workshop')->name('reports.index');
    Route::get('reports/export', ReportExportController::class)->middleware('can:manage-workshop')->name('reports.export');
});

require __DIR__.'/settings.php';
