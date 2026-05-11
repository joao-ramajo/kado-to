<?php

declare(strict_types=1);

use App\Models\User;

test('usuário consegue fazer login', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password'),
    ]);

    $response = $this->postJson(route('api.login', [
        'email' => $user->email,
        'password' => 'password',
    ]));

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'user',
            'token',
        ]);
});
