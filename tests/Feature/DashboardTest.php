<?php

use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\RolesAndPermissionsSeeder;

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('users without a role are sent to the home page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertRedirect('/');
});

test('vendors are sent to their workspace dashboard', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $vendor = Vendor::create(['name' => 'Acme Water', 'slug' => 'acme', 'email' => 'acme@example.com', 'status' => 'active']);
    $user = User::factory()->create(['vendor_id' => $vendor->id]);
    $user->assignRole('vendor');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('vendor.dashboard'));
});

test('super admins are sent to the admin dashboard', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertRedirect(route('admin.dashboard'));
});
