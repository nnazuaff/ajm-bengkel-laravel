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

it('explains workshop booking and check-in in an accessible homepage FAQ', function () {
    $this->get(route('home'))->assertOk()
        ->assertSee('Yang sering ditanyakan')
        ->assertSee('Booking dan check-in bedanya apa?')
        ->assertSee('Booking untuk mengajukan jadwal sebelum datang.')
        ->assertSee('Kalau mau booking, perlu punya akun?')
        ->assertSee('Sudah sampai di bengkel, bagaimana cara check-in?')
        ->assertSee('Bisa langsung datang tanpa booking?')
        ->assertSee('Apa yang harus disiapkan sebelum motor dicek?')
        ->assertSee('Setelah servis, catatannya bisa dilihat di mana?')
        ->assertSee('href="'.route('booking.guest').'"', false)
        ->assertSee('href="'.route('check-in').'"', false)
        ->assertSee('data-slot="faq"', false)
        ->assertSee('data-slot="faq-question"', false)
        ->assertSee('data-slot="faq-answer"', false)
        ->assertSee('<h3 class="ajm-faq-question"><button', false);
});

it('starts with every FAQ answer closed', function () {
    $html = $this->get(route('home'))->assertOk()->getContent();

    expect(substr_count($html, 'aria-expanded="false"'))->toBe(6);
    expect(substr_count($html, 'aria-labelledby="faq-trigger-1" hidden'))->toBe(1);
    expect(substr_count($html, 'aria-labelledby="faq-trigger-6" hidden'))->toBe(1);
});

it('renders six labeled accordion items with animatable answers', function () {
    $html = $this->get(route('home'))->assertOk()->getContent();

    expect(substr_count($html, 'data-slot="faq-item"'))->toBe(6);
    expect(substr_count($html, 'data-slot="faq-question"'))->toBe(6);
    expect(substr_count($html, 'data-slot="faq-answer"'))->toBe(6);
});

it('uses configured contact for working whatsapp and location links without fictional business data', function () {
    WorkshopSetting::current()->update(['phone' => '081234567890', 'address' => 'Alamat Bengkel Uji']);
    $this->get(route('home'))->assertOk()
        ->assertSee('https://wa.me/6281234567890', false)
        ->assertSee('https://www.google.com/maps?q=-6.998307%2C107.5428932', false)
        ->assertSee('Alamat Bengkel Uji')->assertSee('Chat AJM')
        ->assertDontSee('08.00')->assertDontSee('Jl. Kp. Cipongporang');
});

it('omits unconfigured whatsapp without inventing a contact or changing the supplied map pin', function () {
    WorkshopSetting::current()->update(['phone' => '', 'address' => '']);
    $this->get(route('home'))->assertOk()->assertDontSee('wa.me')
        ->assertSee('https://www.google.com/maps?q=-6.998307%2C107.5428932', false);
});

it('embeds the supplied workshop coordinates with accessible lazy loading', function () {
    $this->get(route('home'))->assertOk()
        ->assertSee('https://www.google.com/maps?q=-6.998307%2C107.5428932&amp;z=17&amp;output=embed', false)
        ->assertSee('https://www.google.com/maps?q=-6.998307%2C107.5428932', false)
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
