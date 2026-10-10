<?php

use App\Models\User;
use App\Models\WorkshopSetting;

it('renders a scoped dark homepage with local workshop photos and simple service copy', function () {
    $this->get(route('home'))->assertOk()
        ->assertSee('class="ajm-home ajm-customer"', false)
        ->assertSee('Motor mulai nggak enak dipakai?')
        ->assertSee('Booking servis')->assertSee('Tune up')->assertSee('Kelistrikan')
        ->assertSee('Dudukan shockbreaker custom')
        ->assertSee('/images/workshop/ajm-front-960.webp', false)
        ->assertSee('data-home-menu', false)
        ->assertDontSee('gsap')->assertDontSee('lenis');
});

it('uses configured contact for working whatsapp and location links without fictional business data', function () {
    WorkshopSetting::current()->update(['phone' => '081234567890', 'address' => 'Alamat Bengkel Uji']);
    $this->get(route('home'))->assertOk()
        ->assertSee('https://wa.me/6281234567890', false)
        ->assertSee('https://www.google.com/maps/search/?api=1&amp;query=Alamat%20Bengkel%20Uji', false)
        ->assertSee('Alamat Bengkel Uji')->assertSee('Chat AJM')
        ->assertDontSee('08.00')->assertDontSee('Jl. Kp. Cipongporang');
});

it('omits unconfigured whatsapp and maps links rather than inventing contacts', function () {
    WorkshopSetting::current()->update(['phone' => '', 'address' => '']);
    $this->get(route('home'))->assertOk()->assertDontSee('wa.me')->assertDontSee('maps/search');
});

it('embeds the same workshop coordinates as the previous public site with accessible lazy loading', function () {
    $this->get(route('home'))->assertOk()
        ->assertSee('https://www.google.com/maps?q=-6.9983857%2C107.542855&amp;z=17&amp;output=embed', false)
        ->assertSee('title="Peta lokasi AJM Bengkel"', false)
        ->assertSee('class="ajm-location-map"', false)
        ->assertSee('referrerpolicy="no-referrer-when-downgrade"', false)
        ->assertSee('Lihat rute');
});

it('keeps staff navigation out of customer-only checkin and sends existing customers to their booking', function () {
    $this->actingAs(User::factory()->create(['role' => 'owner']))->get(route('home'))
        ->assertOk()->assertSee(route('dashboard'))->assertDontSee('Check-in di bengkel');
    $this->actingAs(User::factory()->create())->get(route('home'))
        ->assertOk()->assertSee(route('booking.mine'))->assertSee(route('check-in'));
});
