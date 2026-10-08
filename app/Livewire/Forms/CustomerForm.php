<?php

namespace App\Livewire\Forms;

use App\Models\Customer;
use App\Support\WorkshopInput;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Form;

class CustomerForm extends Form
{
    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public string $address = '';

    public string $notes = '';

    /** @return array<string, array<mixed>> */
    public function rules(?Customer $customer = null): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^62[1-9][0-9]{7,12}$/D', Rule::unique('customers', 'phone')->ignore($customer)],
            'email' => ['nullable', 'email', 'max:254'],
            'address' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** @return array<string, mixed> */
    public function validatedData(?Customer $customer = null): array
    {
        $this->name = Str::squish($this->name);
        $this->phone = WorkshopInput::phone($this->phone);
        $this->email = trim($this->email);
        $this->address = trim($this->address);
        $this->notes = trim($this->notes);

        $data = $this->validate($this->rules($customer), attributes: [
            'name' => 'nama', 'phone' => 'nomor telepon', 'email' => 'email',
            'address' => 'alamat', 'notes' => 'catatan',
        ]);

        foreach (['email', 'address', 'notes'] as $field) {
            $data[$field] = $data[$field] === '' ? null : $data[$field];
        }

        return $data;
    }
}
