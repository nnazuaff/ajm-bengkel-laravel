<?php

use App\Models\User;

it('groups owner navigation by workshop activity', function () {
    $html = $this->actingAs(User::factory()->create(['role' => 'owner']))->get('/dashboard')->assertOk()->getContent();
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
    $xpath = new DOMXPath($document);
    foreach ([
        'summary' => ['Dashboard'],
        'operations' => ['Booking', 'Servis', 'Riwayat servis'],
        'masters' => ['Pelanggan', 'Kendaraan', 'Inventori', 'Mekanik / staf'],
        'finance' => ['Bon / penjualan', 'Pembayaran', 'Laporan'],
        'management' => ['Audit log', 'Identitas bengkel'],
    ] as $section => $labels) {
        $group = $xpath->query('//*[@data-sidebar-section="'.$section.'"]');
        expect($group->length)->toBe(1);
        foreach ($labels as $label) {
            expect($group->item(0)->textContent)->toContain($label);
        }
    }
});

it('omits owner settings from admin navigation', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/dashboard')->assertOk()
        ->assertSee('data-sidebar-section="management"', false)->assertSee('Audit log')->assertDontSee('Identitas bengkel');
});

it('does not render empty admin groups for mechanics', function () {
    $this->actingAs(User::factory()->create(['role' => 'mechanic']))->get('/dashboard')->assertOk()
        ->assertSee('data-sidebar-section="summary"', false)->assertSee('data-sidebar-section="operations"', false)
        ->assertDontSee('data-sidebar-section="masters"', false)->assertDontSee('data-sidebar-section="finance"', false)
        ->assertDontSee('data-sidebar-section="management"', false)->assertDontSee('Riwayat servis');
});

it('keeps customer navigation separate from all staff groups', function () {
    $this->actingAs(User::factory()->create())->get('/portal')->assertOk()
        ->assertSee('ajm-customer-nav', false)->assertSee('Booking saya')
        ->assertDontSee('data-sidebar-section="operations"', false)->assertDontSee('data-sidebar-section="masters"', false)
        ->assertDontSee('data-sidebar-section="finance"', false)->assertDontSee('data-sidebar-section="management"', false);
});
