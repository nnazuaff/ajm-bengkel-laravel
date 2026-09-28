<?php

namespace Tests\Feature;

use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\ServicePhotos\ServicePhotoResource;
use App\Filament\Resources\Spareparts\SparepartResource;
use App\Filament\Resources\Transactions\TransactionResource;
use App\Filament\Resources\Vehicles\VehicleResource;
use App\Filament\Resources\WorkOrderItems\WorkOrderItemResource;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Models\AuditLog;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.env' => 'local',
            'app.locale' => 'en',
            'database.default' => 'mysql',
            'database.connections.mysql.database' => 'ajm_bengkel',
        ]);
        DB::setDefaultConnection('mysql');
        DB::purge('mysql');
        app()->setLocale('en');
    }

    public function test_admin_navigation_has_groups_and_icons(): void
    {
        $response = $this->withSession(['locale' => 'en'])
            ->actingAs(User::factory()->make(['id' => 1]))
            ->get('/admin');

        $response->assertOk()
            ->assertSee('Master Data')
            ->assertSee('Operations')
            ->assertSee('Finance')
            ->assertSee('System');

        $this->assertSame('master_data', CustomerResource::getNavigationGroup());
        $this->assertSame('o-user-group', CustomerResource::getNavigationIcon()->value);
        $this->assertSame('o-truck', VehicleResource::getNavigationIcon()->value);
        $this->assertSame('o-cube', SparepartResource::getNavigationIcon()->value);
        $this->assertSame('o-wrench-screwdriver', WorkOrderResource::getNavigationIcon()->value);
        $this->assertSame('o-list-bullet', WorkOrderItemResource::getNavigationIcon()->value);
        $this->assertSame('o-camera', ServicePhotoResource::getNavigationIcon()->value);
        $this->assertSame('o-banknotes', TransactionResource::getNavigationIcon()->value);
        $this->assertSame('o-clock', AuditLogResource::getNavigationIcon()->value);
    }

    public function test_locale_and_navigation_render_on_every_admin_resource_page(): void
    {
        $paths = [
            '/admin',
            '/admin/customers',
            '/admin/customers/create',
            '/admin/vehicles',
            '/admin/vehicles/create',
            '/admin/spareparts',
            '/admin/spareparts/create',
            '/admin/work-orders',
            '/admin/work-orders/create',
            '/admin/work-order-items',
            '/admin/work-order-items/create',
            '/admin/service-photos',
            '/admin/service-photos/create',
            '/admin/transactions',
            '/admin/transactions/create',
            '/admin/audit-logs',
            '/admin/audit-logs/create',
        ];
        $user = User::factory()->make(['id' => 1]);

        foreach ($paths as $path) {
            $response = $this->withSession(['locale' => 'id'])
                ->actingAs($user)
                ->get($path);

            $this->assertSame(200, $response->status(), "Unexpected status for {$path}");
            $response->assertSee('lang="id"', false)
                ->assertSee('Data Master')
                ->assertSee('Bahasa');

            if ($path === '/admin/customers/create') {
                $response->assertSee('Nama')
                    ->assertSee('Telepon');
            }
        }
    }

    public function test_locale_switch_persists_and_applies_to_admin_pages(): void
    {
        $user = User::factory()->make(['id' => 1]);

        $this->actingAs($user)
            ->from('/admin')
            ->get('/locale/id')
            ->assertRedirect('/admin');

        $response = $this->actingAs($user)->get('/admin');

        $response->assertOk()
            ->assertSee('lang="id"', false)
            ->assertSee('Data Master')
            ->assertSee('Pelanggan');

        $this->actingAs($user)
            ->from('/admin')
            ->get('/locale/en')
            ->assertRedirect('/admin');

        $englishResponse = $this->actingAs($user)
            ->get('/admin');

        $englishResponse
            ->assertOk()
            ->assertSee('lang="en"', false)
            ->assertSee('Master Data')
            ->assertSee('Customers');
    }

    public function test_audit_observer_writes_without_an_updated_at_column(): void
    {
        $user = User::query()->firstOrFail();
        $this->actingAs($user);

        DB::beginTransaction();

        try {
            $transaction = Transaction::create([
                'work_order_id' => null,
                'type' => 'income',
                'amount' => 1,
                'description' => 'Audit observer regression test',
                'transaction_date' => now()->toDateString(),
            ]);

            $auditLog = AuditLog::query()
                ->where('table_name', 'transactions')
                ->where('row_id', $transaction->getKey())
                ->latest('id')
                ->firstOrFail();

            $this->assertSame('create', $auditLog->action);
            $this->assertNotNull($auditLog->created_at);
            $this->assertFalse($auditLog->usesTimestamps());
        } finally {
            DB::rollBack();
        }
    }
}
