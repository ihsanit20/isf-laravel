<?php

use App\Models\User;
use Laravel\Fortify\Features;

beforeEach(function () {
    /** @var \Tests\TestCase $this */
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'phone' => '01711111111',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    expect(User::first())->role->toBe('member');
    expect(User::first())->phone->toBe('01711111111');
    $response->assertRedirect(route('dashboard', absolute: false));
});
