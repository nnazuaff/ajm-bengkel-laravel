<?php

use App\Actions\ManageReceipt;
use App\Actions\StockLedger;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\ServiceDocumentation;
use App\Models\ServiceOrder;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;

// Standalone QA router: never loaded by production routes or public/index.php.
$root = dirname(__DIR__);
$qa = getenv('QA_DIR');
if (getenv('APP_ENV') !== 'testing' || ! $qa || ! str_starts_with($qa, $root.'/.hermes/qa/') || strlen(getenv('QA_TOKEN') ?: '') < 64) {
    http_response_code(403);
    exit('QA requires isolated testing configuration.');
}
if (PHP_SAPI !== 'cli' && (PHP_SAPI !== 'cli-server' || ($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1')) {
    http_response_code(403);
    exit;
}
foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $qa.'/database.sqlite', 'SESSION_DRIVER' => 'database', 'SESSION_COOKIE' => 'ajm_qa_'.substr(hash('sha256', $qa), 0, 12), 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array', 'APP_CONFIG_CACHE' => $qa.'/config.php', 'APP_ROUTES_CACHE' => $qa.'/routes.php', 'APP_SERVICES_CACHE' => $qa.'/services.php', 'APP_PACKAGES_CACHE' => $qa.'/packages.php'] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->useStoragePath($qa.'/storage');
foreach (['framework/views', 'framework/sessions', 'framework/cache', 'app/private', 'app/public', 'logs'] as $directory) {
    @mkdir($qa.'/storage/'.$directory, 0700, true);
}
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $qa.'/database.sqlite', 'session.driver' => 'database', 'cache.default' => 'array', 'filesystems.disks.local.root' => $qa.'/storage/app/private', 'filesystems.disks.public.root' => $qa.'/storage/app/public', 'livewire.temporary_file_upload.disk' => 'local', 'filesystems.disks.tmp-for-tests' => ['driver' => 'local', 'root' => $qa.'/storage/app/private/livewire-test-temp', 'throw' => true]]);
Vite::useHotFile($qa.'/no-hot-file');
if (PHP_SAPI === 'cli') {
    if (($argv[1] ?? '') !== 'init' || file_exists($qa.'/database.sqlite')) {
        exit('Use init with a fresh QA_DIR.');
    }
    touch($qa.'/database.sqlite');
    Artisan::call('migrate', ['--force' => true]);
    $owner = User::factory()->create(['name' => 'QA Owner', 'email' => 'qa-owner@example.test', 'role' => 'owner']);
    User::factory()->create(['name' => 'QA Mechanic', 'email' => 'qa-mechanic@example.test', 'role' => 'mechanic']);
    $user = User::factory()->create(['name' => 'QA Customer', 'email' => 'qa-customer@example.test', 'role' => 'customer']);
    $customer = Customer::factory()->create(['name' => 'QA Portal Owner', 'phone' => '628120000001', 'user_id' => $user->id]);
    Vehicle::factory()->create(['customer_id' => $customer->id, 'license_plate' => 'B 1001 QA', 'latest_mileage' => 1000]);
    $other = Customer::factory()->create(['name' => 'QA Other Customer', 'phone' => '628120000002']);
    $vehicle = Vehicle::factory()->create(['customer_id' => $other->id, 'license_plate' => 'B 1002 QA']);
    ServiceOrder::factory()->create(['customer_id' => $other->id, 'vehicle_id' => $vehicle->id, 'received_by' => $owner->id, 'complaint' => 'QA private other complaint']);
    $item = InventoryItem::factory()->create(['sku' => 'QA-OIL', 'name' => 'QA Motor Oil', 'selling_price' => '15000.00']);
    app(StockLedger::class)->move($owner, $item, 20, 'in', 'QA fixture restock');
    $receipt = app(ManageReceipt::class)->create($owner, ['customer_id' => $other->id, 'items' => [['type' => 'custom', 'description' => 'QA Other Private Charge', 'quantity' => 1, 'unit_price' => '10000.00']]]);
    app(ManageReceipt::class)->finalize($owner, $receipt);
    $receptionUser = User::factory()->unverified()->create(['name' => 'QA Reception Customer', 'email' => 'qa-reception@example.test', 'role' => 'customer']);
    $archivedContact = Customer::factory()->create(['name' => 'QA Archived Reception', 'phone' => '628120000003']);
    $archivedContact->delete();
    echo "QA SQLite initialized.\n";
    exit;
}
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$static = realpath($root.'/public'.$path);
if ($path !== '/' && $static && str_starts_with($static, $root.'/public/') && is_file($static)) {
    return false;
}
$check = function (): void {
    abort_unless(hash_equals(getenv('QA_TOKEN'), request()->header('X-QA-Token', '')), 403);
};
Route::middleware('web')->get('/__qa/login/{role}', function (string $role) use ($check) {
    $check();
    abort_unless(in_array($role, ['owner', 'customer', 'mechanic', 'reception'], true), 404);
    $user = User::where('email', 'qa-'.$role.'@example.test')->firstOrFail();
    Auth::login($user);
    request()->session()->regenerate();

    return redirect($role === 'customer' ? '/portal' : '/dashboard');
});
Route::get('/__qa/state', function () use ($check) {
    $check();

    return response()->json([
        'customers' => Customer::get(['id', 'name', 'user_id']),
        'vehicles' => Vehicle::get(['id', 'license_plate', 'customer_id']),
        'orders' => ServiceOrder::get(['id', 'service_number', 'customer_id', 'status', 'diagnosis', 'mechanic_id']),
        'receipts' => Receipt::get(['id', 'customer_id', 'service_order_id', 'status', 'grand_total', 'payment_status']),
        'stock' => InventoryItem::where('sku', 'QA-OIL')->value('current_stock'),
        'movement_count' => StockMovement::count(), 'photo_count' => ServiceDocumentation::count(),
        'payment_count' => Payment::count(), 'booking_count' => Booking::count(),
        'bookings' => Booking::get(['id', 'submitted_by', 'status']),
        'reception_email_verified' => User::where('email', 'qa-reception@example.test')->value('email_verified_at'),
    ]);
});
$app->handleRequest(Request::capture());
