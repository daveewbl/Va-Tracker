<?php

use App\Models\User;

it('serves the Filament login page with the local login link', function () {
    $this->app['env'] = 'local';

    $this->get('/login')
        ->assertOk()
        ->assertSee('Login As:')
        ->assertSee('Test')
        ->assertSee('test@example.com');
});

it('redirects signed-out visitors to login', function () {
    $this->get('/')->assertRedirect('/login');
});

it('authenticates the local test user through the login link', function () {
    $this->app['env'] = 'local';

    $user = User::factory()->create(['email' => 'test@example.com']);
    $this->get('/login');

    $this->post('/laravel-login-link-login', [
        '_token' => csrf_token(),
        'email' => $user->email,
        'redirect_url' => '/',
        'user_attributes' => '[]',
    ])->assertRedirect('/');

    $this->assertAuthenticatedAs($user);
});
