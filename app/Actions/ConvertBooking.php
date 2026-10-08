<?php

namespace App\Actions;

use App\Enums\BookingStatus;
use App\Enums\ServiceSource;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\WorkshopInput;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ConvertBooking
{
    /** @param array<string, mixed> $options */
    public function convert(User $actor, Booking $booking, ?int $mechanicId = null, array $options = []): ServiceOrder
    {
        return DB::transaction(function () use ($actor, $booking, $mechanicId, $options): ServiceOrder {
            $current = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            Gate::forUser($actor)->authorize('convert', $current);
            $existing = ServiceOrder::query()->where('booking_id', $current->id)->first();
            if ($current->status === BookingStatus::ConvertedToService) {
                if ($existing === null || $existing->booking_id !== $current->id || $existing->source !== ServiceSource::Booking) {
                    throw ValidationException::withMessages(['status' => 'Relasi servis booking tidak konsisten. Hubungi admin.']);
                }
                Gate::forUser($actor)->authorize('view', $existing);

                return $existing;
            }
            if ($current->status !== BookingStatus::Arrived || $existing !== null) {
                throw ValidationException::withMessages(['status' => 'Booking harus sudah datang dan belum dikonversi.']);
            }

            $options = Validator::make($options, [
                'link_account' => ['sometimes', 'boolean'],
                'restore_archived' => ['sometimes', 'boolean'],
                'ownership_verified' => ['sometimes', 'boolean'],
            ])->validate();
            $link = (bool) ($options['link_account'] ?? false);
            $restore = (bool) ($options['restore_archived'] ?? false);
            if (($link || $restore) && ! ($options['ownership_verified'] ?? false)) {
                throw ValidationException::withMessages(['ownership_verified' => 'Konfirmasi identitas, motor, dan hak akses histori pelanggan terlebih dahulu.']);
            }
            if ($link && $current->submitted_by === null) {
                throw ValidationException::withMessages(['link_account' => 'Booking ini tidak memiliki akun pelanggan pengirim.']);
            }

            // Lock order: booking, vehicle, customer; ReceiveWalkIn uses the same master lock order.
            $vehicle = Vehicle::withTrashed()->where('license_plate', WorkshopInput::plate($current->license_plate))->lockForUpdate()->first();
            $customer = $vehicle !== null
                ? Customer::withTrashed()->whereKey($vehicle->customer_id)->lockForUpdate()->firstOrFail()
                : Customer::withTrashed()->where('phone', WorkshopInput::phone($current->phone))->lockForUpdate()->first();
            if ($customer !== null && WorkshopInput::phone($customer->phone) !== WorkshopInput::phone($current->phone)) {
                throw ValidationException::withMessages(['phone' => 'Telepon booking berbeda dari pemilik motor terdaftar. Verifikasi pemilik terlebih dahulu.']);
            }
            if ($link && $customer !== null && $customer->user_id !== null && $customer->user_id !== $current->submitted_by) {
                throw ValidationException::withMessages(['link_account' => 'Pelanggan sudah terhubung ke akun lain. Koreksi hubungan melalui menu Pelanggan setelah verifikasi, atau terima tanpa menghubungkan akun.']);
            }
            foreach ([$customer, $vehicle] as $master) {
                if ($master !== null && $master->trashed()) {
                    if (! $restore) {
                        throw ValidationException::withMessages(['restore_archived' => 'Data pelanggan atau motor diarsipkan. Pilih Pulihkan data arsip setelah verifikasi langsung.']);
                    }
                    if (! $master->restore()) {
                        throw new \RuntimeException('Pemulihan data gagal.');
                    }
                    $audit = AuditLog::create(['actor_id' => $actor->id, 'action' => $master instanceof Customer ? 'customer.restored' : 'vehicle.restored',
                        'entity_type' => $master::class, 'entity_id' => $master->id, 'context' => ['booking_id' => $current->id]]);
                    if (! $audit->exists) {
                        throw new \RuntimeException('Audit gagal disimpan.');
                    }
                }
            }
            $input = [
                'current_mileage' => $current->current_mileage, 'complaint' => $current->complaint,
                'notes' => $current->notes, 'mechanic_id' => $mechanicId,
            ];
            if ($vehicle !== null) {
                $input['vehicle_id'] = $vehicle->id;
            } else {
                $input['customer'] = $current->only(['name', 'phone', 'email']);
                $input['vehicle'] = $current->only(['license_plate', 'brand', 'model', 'year']);
            }
            $order = app(ReceiveWalkIn::class)->receive($actor, $input);
            if ($link) {
                $customer = Customer::query()->lockForUpdate()->findOrFail($order->customer_id);
                if ($customer->user_id !== null && $customer->user_id !== $current->submitted_by) {
                    throw ValidationException::withMessages(['link_account' => 'Pelanggan sudah terhubung ke akun lain.']);
                }
                app(LinkCustomerAccount::class)->link($actor, $customer, $current->submitted_by, true);
            }
            // Trusted relationship/source assignment, never browser mass assignment.
            $order->booking_id = $current->id;
            $order->source = ServiceSource::Booking;
            $order->save();
            $current->update(['status' => BookingStatus::ConvertedToService]);
            AuditLog::create([
                'actor_id' => $actor->id, 'action' => 'booking.converted',
                'entity_type' => Booking::class, 'entity_id' => $current->id,
                'context' => ['before' => BookingStatus::Arrived->value, 'after' => BookingStatus::ConvertedToService->value, 'service_order_id' => $order->id, 'source' => ServiceSource::Booking->value],
            ]);

            return $order;
        }, attempts: 5);
    }
}
