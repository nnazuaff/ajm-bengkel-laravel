<?php

// Isolated schema/transaction checks: never migrate or wipe the working database.

use App\Actions\ConvertBooking;
use App\Actions\CreateBooking;
use App\Actions\ManageCheckInCode;
use App\Actions\ManageReceipt;
use App\Actions\NextServiceNumber;
use App\Actions\ProcessCheckIn;
use App\Actions\ReceiveWalkIn;
use App\Actions\SaveServiceJob;
use App\Actions\StockLedger;
use App\Actions\SubmitCheckIn;
use App\Actions\UpdateBooking;
use App\Actions\UpdateServiceOrder;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\ServiceJob;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\ReceiptImage;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function runMigrations(string $command, array $options = []): void
{
    check(Artisan::call($command, ['--database' => 'verification', '--force' => true, ...$options]) === 0, Artisan::output());
}

check(DB::connection()->getDriverName() === 'mysql', 'Configure a MySQL working connection first.');
$originalDatabase = DB::connection()->getDatabaseName();
$database = 'ajm_verify_'.bin2hex(random_bytes(8));
check($database !== $originalDatabase && preg_match('/^ajm_verify_[a-f0-9]{16}$/D', $database) === 1, 'Invalid isolated database name.');
$server = DB::connection();
$created = false;
$exitCode = 0;

try {
    $server->statement('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $created = true;
    $connection = $server->getConfig();
    $connection['database'] = $database;
    $connection['url'] = null;
    $connection['name'] = 'verification';
    config(['database.connections.verification' => $connection, 'cache.default' => 'array', 'session.driver' => 'array']);
    DB::setDefaultConnection('verification');
    check(DB::connection()->getDatabaseName() === $database && DB::connection()->getName() === 'verification', 'Isolation connection mismatch.');
    runMigrations('migrate');
    check(Schema::hasTable('service_orders') && Schema::hasColumn('users', 'role'), 'Initial MySQL migration failed.');

    runMigrations('migrate:rollback', ['--step' => count(glob(dirname(__DIR__).'/database/migrations/2026_*.php') ?: [])]);
    check(! Schema::hasTable('customers') && ! Schema::hasColumn('users', 'role'), 'Dependency-ordered rollback failed.');
    runMigrations('migrate');
    check(Schema::hasTable('vehicles') && Schema::hasTable('audit_logs'), 'MySQL migration reapply failed.');
    echo "PASS MySQL migrations / domain rollback / reapply\n";
    $databaseObjects = require dirname(__DIR__).'/database/migrations/2026_10_07_000019_create_workshop_database_objects.php';
    $databaseObjects->up();
    DB::unprepared('DROP TRIGGER IF EXISTS stock_movement_audit');
    $databaseObjects->up();
    check(count(DB::select("SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = 'stock_movement_audit'")) === 1, 'Retry did not recreate missing trigger.');
    echo "PASS MySQL existing routines / repeated migration / partial retry\n";

    $actor = User::factory()->create(['role' => 'admin']);
    $vehicle = Vehicle::factory()->create(['latest_mileage' => 1000]);
    $order = app(ReceiveWalkIn::class)->receive($actor, ['vehicle_id' => $vehicle->id, 'current_mileage' => 1200, 'complaint' => 'Periksa rem.']);
    check($vehicle->fresh()->latest_mileage === 1200 && $order->customer_id === $vehicle->customer_id, 'Intake snapshot failed.');
    try {
        app(ReceiveWalkIn::class)->receive($actor, ['vehicle_id' => $vehicle->id, 'current_mileage' => 1300, 'complaint' => 'Duplicate intake.']);
        throw new RuntimeException('Active order must reject duplicate intake.');
    } catch (ValidationException) {
        check(ServiceOrder::count() === 1 && $vehicle->fresh()->latest_mileage === 1200, 'Rejected intake changed data.');
    }

    $baseline = [Customer::count(), Vehicle::count(), ServiceOrder::count(), DB::table('document_sequences')->sum('value')];
    AuditLog::creating(fn () => throw new RuntimeException('Simulated audit outage'));
    try {
        app(ReceiveWalkIn::class)->receive($actor, [
            'customer' => ['name' => 'Pelanggan verifikasi', 'phone' => '081234567899'],
            'vehicle' => ['license_plate' => 'B9987CHECK', 'brand' => 'Honda', 'model' => 'Beat'],
            'current_mileage' => 500,
            'complaint' => 'Rollback check.',
        ]);
        throw new RuntimeException('Audit failure did not propagate.');
    } catch (RuntimeException $exception) {
        check($exception->getMessage() === 'Simulated audit outage', $exception->getMessage());
    } finally {
        AuditLog::flushEventListeners();
    }
    check($baseline === [Customer::count(), Vehicle::count(), ServiceOrder::count(), DB::table('document_sequences')->sum('value')], 'Intake rollback left partial data.');
    echo "PASS MySQL intake / duplicate guard / atomic audit rollback\n";

    foreach (['inspection', 'approved', 'in_progress', 'completed', 'ready_for_pickup', 'delivered'] as $status) {
        $order = app(UpdateServiceOrder::class)->update($actor, $order, ['status' => $status, 'diagnosis' => 'Kampas rem diperiksa.']);
    }
    check($order->started_at !== null && $order->completed_at !== null && $order->delivered_at !== null, 'Workflow timestamps missing.');
    check(AuditLog::where('action', 'service.updated')->count() === 6, 'Workflow audit history missing.');
    echo "PASS MySQL workflow / timestamps / audit history\n";

    $customerAccount = User::factory()->create();
    $booking = app(CreateBooking::class)->create($customerAccount, [
        'name' => 'Pelanggan booking verifikasi', 'phone' => '081299998888',
        'license_plate' => 'B9988CHECK', 'brand' => 'Honda', 'model' => 'Beat',
        'current_mileage' => 800, 'booking_date' => now()->addDay()->toDateString(),
        'arrival_time' => '09:00', 'service_type' => 'Servis rem', 'complaint' => 'Rem berisik.',
    ]);
    check(Customer::where('phone', '6281299998888')->exists() && $booking->vehicle_id !== null, 'Booking did not resolve master records.');
    $booking = app(UpdateBooking::class)->update($actor, $booking, ['status' => 'confirmed']);
    $booking = app(UpdateBooking::class)->update($actor, $booking, ['status' => 'arrived']);
    $converted = app(ConvertBooking::class)->convert($actor, $booking, null, ['link_account' => true, 'ownership_verified' => true]);
    check($converted->customer->user_id === $customerAccount->id, 'Trusted reception did not link the submitted account.');
    $repeated = app(ConvertBooking::class)->convert($actor, $booking);
    check($converted->id === $repeated->id && $converted->source->value === 'booking' && $converted->booking_id === $booking->id, 'Booking conversion not idempotent.');
    check(ServiceOrder::where('booking_id', $booking->id)->count() === 1, 'Booking conversion duplicated order.');
    echo "PASS MySQL booking snapshot / arrival / idempotent conversion\n";

    $jobA = app(SaveServiceJob::class)->save($actor, $converted, ['name' => 'Bersihkan rem', 'labor_price' => '0.10', 'status' => 'pending']);
    $jobB = app(SaveServiceJob::class)->save($actor, $converted, ['name' => 'Periksa rem', 'labor_price' => '0.20', 'status' => 'pending']);
    $sum = ServiceJob::where('service_order_id', $converted->id)->selectRaw('CAST(SUM(labor_price) AS CHAR) as total')->first()->getAttribute('total');
    check($sum === '0.30', 'MySQL decimal subtotal lost precision.');
    foreach (['inspection', 'approved', 'in_progress'] as $status) {
        $converted = app(UpdateServiceOrder::class)->update($actor, $converted, ['status' => $status, 'diagnosis' => 'Kampas rem diperiksa.']);
    }
    try {
        app(UpdateServiceOrder::class)->update($actor, $converted, ['status' => 'completed']);
        throw new RuntimeException('Unfinished jobs must block completion.');
    } catch (ValidationException) {
    }
    app(SaveServiceJob::class)->save($actor, $converted, ['status' => 'completed'], $jobA);
    app(SaveServiceJob::class)->save($actor, $converted, ['status' => 'cancelled'], $jobB);
    $sum = ServiceJob::where('service_order_id', $converted->id)->where('status', '!=', 'cancelled')->selectRaw('CAST(SUM(labor_price) AS CHAR) as total')->first()->getAttribute('total');
    check($sum === '0.10', 'Cancelled job remained in subtotal.');
    $converted = app(UpdateServiceOrder::class)->update($actor, $converted, ['status' => 'completed']);
    try {
        app(SaveServiceJob::class)->save($actor, $converted, ['name' => 'Late change', 'labor_price' => '1.00', 'status' => 'pending']);
        throw new RuntimeException('Completed order must lock jobs.');
    } catch (ValidationException) {
    }
    echo "PASS MySQL service jobs / exact decimal SUM / completion guard / terminal lock\n";

    $numbers = [];
    for ($index = 0; $index < 10; $index++) {
        $numbers[] = app(NextServiceNumber::class)->generate();
    }
    check(count(array_unique($numbers)) === 10, 'Sequential document numbers are not unique.');
    echo "PASS MySQL daily document sequence\n";

    $stockItem = InventoryItem::factory()->create(['current_stock' => 0, 'selling_price' => '100.25']);
    $movement = app(StockLedger::class)->move($actor, $stockItem, 5, 'in', 'purchase');
    check(AuditLog::where('action', 'stock_movement.created')->where('entity_id', $movement->id)->count() === 1, 'MySQL stock audit trigger missing or duplicated.');
    $receipt = app(ManageReceipt::class)->create($actor, [
        'items' => [['type' => 'product', 'inventory_item_id' => $stockItem->id, 'quantity' => 2]],
    ]);
    $receipt = app(ManageReceipt::class)->finalize($actor, $receipt);
    check($stockItem->fresh()->current_stock === 3, 'Direct sale did not deduct stock.');
    $png = app(ReceiptImage::class)->render($receipt);
    $imageInfo = getimagesizefromstring($png);
    check($imageInfo !== false && $imageInfo[2] === IMAGETYPE_PNG && $imageInfo[0] === 800, 'Real receipt PNG verification failed.');
    $output = storage_path('app/verification');
    if (! is_dir($output)) {
        mkdir($output, 0755, true);
    }
    file_put_contents($output.'/receipt-proof.png', $png);
    echo 'ARTIFACT '.$output.'/receipt-proof.png'.PHP_EOL;
    check(DB::selectOne('SELECT workshop_receipt_balance(?) AS balance', [$receipt->id])->balance === '200.50', 'Balance function differs from receipt source of truth.');
    app(ManageReceipt::class)->pay($actor, $receipt, ['amount' => '200.50', 'method' => 'cash', 'paid_at' => now()->toDateTimeString()]);
    check(DB::selectOne('SELECT workshop_receipt_balance(?) AS balance', [$receipt->id])->balance === '0.00', 'Balance function did not include payment.');
    $pdo = DB::connection()->getPdo();
    $statement = $pdo->prepare('CALL workshop_daily_payments(?, ?)');
    $statement->execute([now()->toDateString(), now()->toDateString()]);
    $daily = $statement->fetchAll(PDO::FETCH_ASSOC);
    $statement->closeCursor();
    check(count($daily) === 1 && $daily[0]['gross'] === '200.50' && $daily[0]['net'] === '200.50', 'Stored procedure daily report incorrect.');
    $owner = User::factory()->create(['role' => 'owner']);
    app(ManageReceipt::class)->void($owner, $receipt, 'Void verifikasi');
    check($stockItem->fresh()->current_stock === 5, 'Void direct sale did not reverse stock.');
    check(DB::selectOne('SELECT workshop_receipt_balance(?) AS balance', [$receipt->id])->balance === '0.00', 'Voided balance function wrong.');
    $statement = $pdo->prepare('CALL workshop_daily_payments(?, ?)');
    $statement->execute([now()->toDateString(), now()->toDateString()]);
    $daily = $statement->fetchAll(PDO::FETCH_ASSOC);
    $statement->closeCursor();
    check($daily[0]['gross'] === '200.50' && $daily[0]['reversed'] === '200.50' && $daily[0]['net'] === '0.00', 'Procedure omitted reversals.');
    echo "PASS MySQL function / procedure / audit trigger / paid direct-sale void\n";

    $raceItem = InventoryItem::factory()->create(['current_stock' => 0]);
    app(StockLedger::class)->move($actor, $raceItem, 2, 'in', 'purchase');
    $raceReceipt = app(ManageReceipt::class)->create($actor, ['items' => [['type' => 'custom', 'description' => 'Race payment', 'quantity' => 1, 'unit_price' => '100.00']]]);
    $concurrentBooking = Booking::factory()->create(['status' => 'arrived', 'phone' => '6281211112222', 'license_plate' => 'B1122CHECK']);
    check(function_exists('pcntl_fork'), 'pcntl is required for the concurrency check.');
    DB::disconnect('verification');
    $workers = [];
    for ($worker = 0; $worker < 4; $worker++) {
        $pipes = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        check($pipes !== false, 'Unable to create worker pipe.');
        $pid = pcntl_fork();
        check($pid !== -1, 'Unable to fork sequence worker.');
        if ($pid === 0) {
            fclose($pipes[0]);
            try {
                DB::purge('verification');
                $batch = [];
                for ($index = 0; $index < 10; $index++) {
                    $batch[] = app(NextServiceNumber::class)->generate();
                }
                $concurrentOrder = app(ConvertBooking::class)->convert($actor, $concurrentBooking);
                $deducted = false;
                try {
                    app(StockLedger::class)->move($actor, $raceItem, -1, 'out', 'service');
                    $deducted = true;
                } catch (ValidationException) {
                }
                $finalized = false;
                try {
                    app(ManageReceipt::class)->finalize($actor, $raceReceipt);
                    $finalized = true;
                } catch (ValidationException) {
                }
                $paid = false;
                try {
                    app(ManageReceipt::class)->pay($actor, $raceReceipt, ['amount' => '100.00', 'method' => 'cash', 'paid_at' => now()->toDateTimeString()]);
                    $paid = true;
                } catch (ValidationException) {
                }
                fwrite($pipes[1], json_encode(['numbers' => $batch, 'order_id' => $concurrentOrder->id, 'deducted' => $deducted, 'finalized' => $finalized, 'paid' => $paid], JSON_THROW_ON_ERROR));
                fclose($pipes[1]);
                exit(0);
            } catch (Throwable $exception) {
                fwrite($pipes[1], json_encode(['error' => strtok($exception->getMessage(), PHP_EOL)], JSON_THROW_ON_ERROR));
                fclose($pipes[1]);
                exit(1);
            }
        }
        fclose($pipes[1]);
        $workers[] = [$pid, $pipes[0]];
    }
    $concurrentNumbers = [];
    $convertedIds = [];
    $successfulDeductions = 0;
    $successfulFinalizations = 0;
    $successfulPayments = 0;
    $workerErrors = [];
    foreach ($workers as [$pid, $pipe]) {
        $batch = stream_get_contents($pipe);
        fclose($pipe);
        pcntl_waitpid($pid, $status);
        $result = json_decode($batch, true, flags: JSON_THROW_ON_ERROR);
        if (! pcntl_wifexited($status) || pcntl_wexitstatus($status) !== 0) {
            $workerErrors[] = $result['error'] ?? 'Worker exit failure';
        } else {
            $concurrentNumbers = [...$concurrentNumbers, ...$result['numbers']];
            $convertedIds[] = $result['order_id'];
            $successfulDeductions += $result['deducted'] ? 1 : 0;
            $successfulFinalizations += $result['finalized'] ? 1 : 0;
            $successfulPayments += $result['paid'] ? 1 : 0;
        }
    }
    check($workerErrors === [], implode('; ', $workerErrors));
    check(count($concurrentNumbers) === 40 && count(array_unique($concurrentNumbers)) === 40, 'Concurrent numbers collided.');
    check(count($convertedIds) === 4 && count(array_unique($convertedIds)) === 1, 'Concurrent conversion duplicated service orders.');
    check(ServiceOrder::where('booking_id', $concurrentBooking->id)->count() === 1, 'Unique booking relationship violated.');
    echo "PASS MySQL concurrent document sequence: 4 workers / 40 unique allocations\n";
    echo "PASS MySQL concurrent booking conversion: 4 workers / 1 service order\n";
    check($successfulDeductions === 2 && $raceItem->fresh()->current_stock === 0, 'Concurrent stock check oversold inventory.');
    echo "PASS MySQL concurrent stock: 4 attempts / 2 deductions / no negative stock\n";
    check($successfulFinalizations === 1 && $successfulPayments === 1 && $raceReceipt->payments()->count() === 1, 'Concurrent finance duplicated finalization/payment.');
    check($raceReceipt->fresh()->status->value === 'paid', 'Concurrent payment did not settle receipt.');
    echo "PASS MySQL concurrent finance: 4 attempts / 1 finalization / 1 payment\n";
    $mechanic = User::factory()->create(['role' => 'mechanic']);
    $code = app(ManageCheckInCode::class)->generate($mechanic);
    $input = ['code' => $code->code, 'name' => 'Check-in verification', 'phone' => '081200001234'];
    $entry = app(SubmitCheckIn::class)->submit($input);
    check(app(SubmitCheckIn::class)->submit($input)->id === $entry->id, 'Repeated check-in duplicated queue.');
    $serviceInput = ['vehicle' => ['license_plate' => 'D9010MYSQL', 'brand' => 'Honda', 'model' => 'Vario'], 'current_mileage' => 10, 'complaint' => 'Check-in verification', 'identity_verified' => true];
    $checkInOrder = app(ProcessCheckIn::class)->process($mechanic, $entry, $serviceInput);
    check(app(ProcessCheckIn::class)->process($mechanic, $entry, $serviceInput)->id === $checkInOrder->id && $checkInOrder->mechanic_id === $mechanic->id, 'Check-in conversion identity/idempotency failed.');
    check($code->getRawOriginal('code') !== $code->code, 'Check-in code stored unencrypted.');
    echo "PASS MySQL check-in encrypted code / duplicate guard / mechanic conversion\n";
} catch (Throwable $exception) {
    $exitCode = 1;
    fwrite(STDERR, 'FAIL '.$exception->getMessage().PHP_EOL);
} finally {
    DB::disconnect('verification');
    if ($created) {
        $server->statement('DROP DATABASE `'.$database.'`');
        echo "CLEANUP isolated verification database; working data preserved\n";
    }
}

exit($exitCode);
