<?php

use Laravel\Fortify\Features;

test('self registration is disabled', function () {
    expect(Features::enabled(Features::registration()))->toBeFalse();

    $this->get('/register')->assertNotFound();
});

test('the login page offers request access instead of sign up', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee(route('access.request'), false);
});
