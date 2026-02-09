<?php

use App\Models\City;
use App\Models\FirstName;

beforeEach(function () {
    // Ensure at least one city and first name exist
    City::firstOrCreate(['name' => 'Test City']);
    FirstName::firstOrCreate(['name' => 'Test First Name'], ['gender' => 'male']);
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
    $response->assertSee('City');
    $response->assertSee('First Name');
});

test('new users can register', function () {
    $city = City::firstOrCreate(['name' => 'Budapest']);
    $firstName = FirstName::firstOrCreate(['name' => 'Albert'], ['gender' => 'male']);

    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'city_id' => $city->id,
        'first_name_id' => $firstName->id,
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', [
        'email' => 'test@example.com',
        'city_id' => $city->id,
        'first_name_id' => $firstName->id,
    ]);
});

test('registration requires city and first name', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test2@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        // missing city_id and first_name_id
    ]);

    $response->assertSessionHasErrors(['city_id', 'first_name_id']);
});

test('city and first name combination must be unique', function () {
    $city = City::firstOrCreate(['name' => 'Paris']);
    $firstName = FirstName::firstOrCreate(['name' => 'Marie'], ['gender' => 'female']);

    // Create first user with this combination
    $this->post(route('register.store'), [
        'name' => 'Alice',
        'email' => 'alice@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'city_id' => $city->id,
        'first_name_id' => $firstName->id,
    ]);

    // Log out the first user to simulate a new registration attempt
    auth()->logout();

    // Attempt to create second user with same combination
    $response = $this->post(route('register.store'), [
        'name' => 'Bob',
        'email' => 'bob@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'city_id' => $city->id,
        'first_name_id' => $firstName->id,
    ]);

    // Check if the second request succeeded (should not)
    $this->assertDatabaseMissing('users', [
        'email' => 'bob@example.com',
    ]);

    $response->assertSessionHasErrors(['city_id', 'first_name_id']);
});
