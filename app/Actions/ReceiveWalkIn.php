<?php

namespace App\Actions;

use App\Enums\Role;
use App\Enums\ServiceSource;
use App\Enums\ServiceStatus;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\WorkshopInput;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReceiveWalkIn
{
    /** @param array<string, mixed> $input */
    public function receive(User $actor, array $input): ServiceOrder
    {
        Gate::forUser($actor)->authorize('create', ServiceOrder::class);

        return $this->intake($actor, $input);
    }

    /** @param array<string, mixed> $input */
    public function receiveFromCheckIn(User $actor, array $input): ServiceOrder
    {
        Gate::forUser($actor)->authorize('work-services');
        $actor = User::query()->findOrFail($actor->id);
        if ($actor->role === Role::Mechanic) {
            $input['mechanic_id'] = $actor->id;
        }

        return $this->intake($actor, $input);
    }

    /** @param array<string, mixed> $input */
    private function intake(User $actor, array $input): ServiceOrder
    {
        if (isset($input['customer']['phone']) && is_string($input['customer']['phone'])) {
            $input['customer']['phone'] = WorkshopInput::phone($input['customer']['phone']);
        }
        if (isset($input['vehicle']['license_plate']) && is_string($input['vehicle']['license_plate'])) {
            $input['vehicle']['license_plate'] = WorkshopInput::plate($input['vehicle']['license_plate']);
        }

        $newVehicle = empty($input['vehicle_id']);
        $newCustomer = $newVehicle && empty($input['customer_id']);
        $data = Validator::make($input, [
            'vehicle_id' => ['nullable', 'integer', Rule::exists('vehicles', 'id')->whereNull('deleted_at')],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'customer' => [Rule::requiredIf($newCustomer), 'array:name,phone,email,address,notes'],
            'customer.name' => [Rule::requiredIf($newCustomer), 'string', 'max:120'],
            'customer.phone' => [Rule::requiredIf($newCustomer), 'string', 'max:20', 'regex:/^62[1-9][0-9]{7,12}$/D'],
            'customer.email' => ['nullable', 'email:rfc', 'max:254'],
            'customer.address' => ['nullable', 'string', 'max:2000'],
            'customer.notes' => ['nullable', 'string', 'max:5000'],
            'vehicle' => [Rule::requiredIf($newVehicle), 'array:license_plate,brand,model,year,color,chassis_number,engine_number,notes'],
            'vehicle.license_plate' => [Rule::requiredIf($newVehicle), 'string', 'max:20', 'regex:/^[A-Z0-9]+$/D'],
            'vehicle.brand' => [Rule::requiredIf($newVehicle), 'string', 'max:60'],
            'vehicle.model' => [Rule::requiredIf($newVehicle), 'string', 'max:100'],
            'vehicle.year' => ['nullable', 'integer', 'between:1900,'.(now()->year + 1)],
            'vehicle.color' => ['nullable', 'string', 'max:50'],
            'vehicle.chassis_number' => ['nullable', 'string', 'max:100'],
            'vehicle.engine_number' => ['nullable', 'string', 'max:100'],
            'vehicle.notes' => ['nullable', 'string', 'max:5000'],
            'current_mileage' => ['required', 'integer', 'between:0,2147483647'],
            'complaint' => ['required', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'mechanic_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', Role::Mechanic->value)->whereNull('deleted_at')],
        ])->validate();

        try {
            return DB::transaction(function () use ($actor, $data, $newVehicle): ServiceOrder {
                if (! $newVehicle) {
                    $vehicle = Vehicle::query()->lockForUpdate()->whereKey($data['vehicle_id'])->firstOrFail();
                    $customer = Customer::query()->lockForUpdate()->findOrFail($vehicle->customer_id);
                    if (isset($data['customer_id']) && (int) $data['customer_id'] !== $customer->id) {
                        throw ValidationException::withMessages(['vehicle_id' => 'Motor bukan milik pelanggan yang dipilih.']);
                    }
                } else {
                    $customer = isset($data['customer_id'])
                        ? Customer::query()->lockForUpdate()->whereKey($data['customer_id'])->firstOrFail()
                        : app(CustomerResolver::class)->resolve($this->nullOptionals($data['customer']));
                    $vehicle = app(VehicleResolver::class)->resolve($customer, [
                        ...$this->nullOptionals($data['vehicle']),
                        'current_mileage' => $data['current_mileage'],
                    ]);
                }

                if ((int) $data['current_mileage'] < $vehicle->latest_mileage) {
                    throw ValidationException::withMessages(['current_mileage' => 'Kilometer tidak boleh lebih kecil dari catatan terakhir.']);
                }

                if ($vehicle->serviceOrders()->whereNotIn('status', [ServiceStatus::Delivered->value, ServiceStatus::Cancelled->value])->exists()) {
                    throw ValidationException::withMessages(['vehicle_id' => 'Kendaraan masih memiliki servis aktif. Buka order yang sudah ada.']);
                }

                $vehicle->update(['latest_mileage' => $data['current_mileage']]);
                $order = ServiceOrder::create([
                    'service_number' => app(NextServiceNumber::class)->generate(),
                    'customer_id' => $customer->id,
                    'vehicle_id' => $vehicle->id,
                    'mechanic_id' => $data['mechanic_id'] ?? null,
                    'received_by' => $actor->id,
                    'source' => ServiceSource::WalkIn,
                    'current_mileage' => $data['current_mileage'],
                    'complaint' => trim($data['complaint']),
                    'notes' => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
                    'status' => ServiceStatus::Waiting,
                    'received_at' => now(),
                ]);
                $audit = AuditLog::create([
                    'actor_id' => $actor->id,
                    'action' => 'service.received',
                    'entity_type' => ServiceOrder::class,
                    'entity_id' => $order->id,
                    'context' => ['source' => ServiceSource::WalkIn->value, 'status' => ServiceStatus::Waiting->value],
                ]);

                if (! $audit->exists) {
                    throw new \RuntimeException('Audit gagal disimpan.');
                }

                return $order;
            }, attempts: 5);
        } catch (ValidationException $exception) {
            $errors = [];
            foreach ($exception->errors() as $key => $messages) {
                $field = $key === 'phone' ? 'customer.phone' : ($key === 'license_plate' ? 'vehicle.license_plate' : $key);
                $errors[$field] = $messages;
            }
            throw ValidationException::withMessages($errors);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['vehicle.license_plate' => 'Data telah dicatat oleh petugas lain. Cari kembali plat atau nomor telepon.']);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function nullOptionals(array $data): array
    {
        return array_map(fn ($value) => is_string($value) ? (trim($value) === '' ? null : trim($value)) : $value, $data);
    }
}
