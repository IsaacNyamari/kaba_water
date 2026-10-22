<?php

use App\Models\User;

test('the app signs in with email and password and the handoff link opens a web session once', function () {
    $user = accountingUser(accountingVendor());
    $user->forceFill(['password' => 'secret-pass'])->save();

    $response = $this->postJson(route('mobile.login'), ['email' => strtoupper($user->email), 'password' => 'secret-pass'])
        ->assertOk()
        ->assertJsonStructure(['name', 'session_url']);

    $sessionUrl = $response->json('session_url');

    $this->get($sessionUrl)->assertRedirect($user->homeRoute());
    $this->assertAuthenticatedAs($user);

    auth()->logout();
    $this->get($sessionUrl)->assertRedirect(route('login'));
    $this->assertGuest();
});

test('wrong credentials are refused without saying which part was wrong', function () {
    $user = User::factory()->create(['password' => 'right-pass']);

    $this->postJson(route('mobile.login'), ['email' => $user->email, 'password' => 'wrong'])
        ->assertStatus(422)
        ->assertJson(['message' => trans('auth.failed')]);

    $this->postJson(route('mobile.login'), ['mode' => 'phone', 'phone' => '0700000000'])
        ->assertStatus(422);
});

test('the version endpoint tells the app where the latest APK is', function () {
    $this->getJson(route('mobile.version'))->assertOk()->assertJsonStructure(['latest_version', 'download_url']);
});
