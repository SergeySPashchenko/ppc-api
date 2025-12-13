<?php

use App\Models\User;
use Database\Seeders\DataSeeder;
use Database\Seeders\PermissionSeeder;
use Laravel\Sanctum\Sanctum;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(DataSeeder::class);
});

test('user can login with valid credentials', function () {
    $user = User::where('email', 'admin@example.com')->first();

    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'user' => ['id', 'name', 'email', 'slug'],
        'token',
    ]);
    expect($response->json('user.email'))->toBe($user->email);
    expect($response->json('token'))->not->toBeEmpty();
});

test('user cannot login with invalid credentials', function () {
    $user = User::where('email', 'admin@example.com')->first();

    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['email']);
});

test('user can register with valid data', function () {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'New User',
        'email' => 'newuser@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(201);
    $response->assertJsonStructure([
        'user' => ['id', 'name', 'email', 'slug'],
        'token',
    ]);
    expect($response->json('user.email'))->toBe('newuser@example.com');
    expect($response->json('token'))->not->toBeEmpty();

    // Verify user was created
    $this->assertDatabaseHas('users', [
        'email' => 'newuser@example.com',
    ]);
});

test('user cannot register with duplicate email', function () {
    $existingUser = User::where('email', 'admin@example.com')->first();

    $response = $this->postJson('/api/auth/register', [
        'name' => 'New User',
        'email' => $existingUser->email,
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['email']);
});

test('user can logout', function () {
    $user = User::where('email', 'admin@example.com')->first();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/auth/logout');

    $response->assertSuccessful();
    $response->assertJson(['message' => 'Logged out successfully']);
});

test('user can get authenticated user info', function () {
    $user = User::where('email', 'admin@example.com')->first();
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/auth/user');

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'user' => ['id', 'name', 'email', 'slug', 'is_global_admin'],
    ]);
    expect($response->json('user.email'))->toBe($user->email);
    expect($response->json('user.is_global_admin'))->toBeTrue();
});

test('unauthenticated user cannot access protected routes', function () {
    $response = $this->getJson('/api/auth/user');

    $response->assertStatus(401);
});

test('unauthenticated user cannot logout', function () {
    $response = $this->postJson('/api/auth/logout');

    $response->assertStatus(401);
});
