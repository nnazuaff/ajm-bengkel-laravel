<?php

namespace App\Livewire\Forms;

use App\Models\Vehicle;
use App\Support\WorkshopInput;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Form;

class VehicleForm extends Form
{
    public string|int $customer_id = '';

    public string $license_plate = '';

    public string $brand = '';

    public string $model = '';

    public string|int $year = '';

    public string $color = '';

    public string $chassis_number = '';

    public string $engine_number = '';

    public string|int $latest_mileage = 0;

    public string $notes = '';

    /** @return array<string, array<mixed>> */
    public function rules(?Vehicle $vehicle = null): array
    {
        return [
            'customer_id' => [
                'required', 'integer', Rule::exists('customers', 'id')->whereNull('deleted_at'),
                ...($vehicle !== null && $vehicle->serviceOrders()->exists() ? [Rule::in([$vehicle->customer_id])] : []),
            ],
            'license_plate' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9]+$/D', Rule::unique('vehicles', 'license_plate')->ignore($vehicle)],
            'brand' => ['required', 'string', 'max:60'],
            'model' => ['required', 'string', 'max:100'],
            'year' => ['nullable', 'integer', 'between:1900,'.(now()->year + 1)],
            'color' => ['nullable', 'string', 'max:50'],
            'chassis_number' => ['nullable', 'string', 'max:100'],
            'engine_number' => ['nullable', 'string', 'max:100'],
            'latest_mileage' => ['required', 'integer', 'between:0,2147483647', 'min:'.($vehicle === null ? 0 : $vehicle->latest_mileage)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** @return array<string, mixed> */
    public function validatedData(?Vehicle $vehicle = null): array
    {
        $this->license_plate = WorkshopInput::plate($this->license_plate);
        $this->brand = Str::squish($this->brand);
        $this->model = Str::squish($this->model);
        foreach (['color', 'chassis_number', 'engine_number', 'notes'] as $field) {
            $this->$field = trim($this->$field);
        }

        $data = $this->validate($this->rules($vehicle), attributes: [
            'customer_id' => 'pelanggan', 'license_plate' => 'pelat nomor', 'brand' => 'merek',
            'model' => 'tipe motor', 'year' => 'tahun', 'color' => 'warna',
            'chassis_number' => 'nomor rangka', 'engine_number' => 'nomor mesin',
            'latest_mileage' => 'odometer', 'notes' => 'catatan',
        ]);

        foreach (['year', 'color', 'chassis_number', 'engine_number', 'notes'] as $field) {
            $data[$field] = $data[$field] === '' ? null : $data[$field];
        }
        $data['customer_id'] = (int) $data['customer_id'];
        $data['latest_mileage'] = (int) $data['latest_mileage'];
        $data['year'] = $data['year'] === null ? null : (int) $data['year'];

        return $data;
    }
}
