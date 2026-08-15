<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('office login page still loads after the panel theme swap', function () {
    $this->get('/login')
        ->assertOk()
        ->assertDontSee('Northwestern')
        ->assertSee('Designed and Developed By:')
        ->assertSee('Moshiur Rahman Tonmoy');
});

test('authenticated owners can still open the dashboard with the new theme shell', function () {
    $this->seed(AuthorizationSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('owner');

    $this->actingAs($user)
        ->get('/')
        ->assertOk()
        ->assertSee('Install App')
        ->assertSee('Dashboard')
        ->assertDontSee('Northwestern')
        ->assertSee('Designed and Developed By:')
        ->assertSee('Moshiur Rahman Tonmoy');
});
