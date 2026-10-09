<?php

namespace App\Actions;

use App\Models\Customer;
use App\Models\Vehicle;
use App\Support\WorkshopInput;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class VehicleResolver
{
    /** @param array<string, mixed> $input */
    public function resolve(Customer $customer, array $input): Vehicle
    {
        $input['license_plate'] = is_string($input['license_plate'] ?? null) ? WorkshopInput::plate($input['license_plate']) : null;
        $data = Validator::make($input, [
            'license_plate' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9]+$/D'],
            'brand' => ['required', 'string', 'max:60'], 'model' => ['required', 'string', 'max:100'],
            'year' => ['nullable', 'integer', 'between:1900,'.(now()->year + 1)],
            'color' => ['nullable', 'string', 'max:50'], 'chassis_number' => ['nullable', 'string', 'max:100'],
            'engine_number' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string', 'max:5000'],
            'current_mileage' => ['sometimes', 'integer', 'between:0,2147483647'],
        ])->validate();
        $find = function () use ($customer, $data): ?Vehicle {
            $vehicle = Vehicle::withTrashed()->where('license_plate', $data['license_plate'])->lockForUpdate()->first();
            if ($vehicle && ($vehicle->trashed() || $vehicle->customer_id !== $customer->id)) {
                throw ValidationException::withMessages(['license_plate' => 'Motor perlu dikonfirmasi petugas. Data pemilik tidak diubah otomatis.']);
            }

            return $vehicle;
        };
        try {
            return DB::transaction(function () use ($customer, $data, $find): Vehicle {
                $customer = Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
                if ($existing = $find()) {
                    return $existing;
                }
                $mileage = $data['current_mileage'] ?? 0;
                unset($data['current_mileage']);

                return $customer->vehicles()->create([...$data, 'latest_mileage' => $mileage]);
            }, attempts: 5);
        } catch (UniqueConstraintViolationException) {
            return $find() ?? throw ValidationException::withMessages(['license_plate' => 'Data sedang diproses. Silakan coba lagi.']);
        }
    }
}
