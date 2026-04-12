<?php

use App\Models\User;
use Laravel\Fortify\Features;

test('registration feature is disabled', function () {
    expect(Features::enabled(Features::registration()))->toBeFalse();
});

test('registration screen is not available', function () {
    $this->get('/register')->assertNotFound();
});

test('new users cannot register', function () {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    expect(User::where('email', 'test@example.com')->exists())->toBeFalse();
    $this->assertGuest();
});
