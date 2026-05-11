<?php

declare(strict_types=1);

use App\Models\User;

test('cria uma conta de usuário com sucesso e retorna seu nome e token', function () {
    $response = $this->postJson(route('api.register', [
        'name' => 'John Doe',
        'email' => 'john.doe@gmail.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => true,
    ]));

    $response->assertJsonStructure([
        'message',
        'user' => ['name'],
        'token',
    ]);

    $this->assertDatabaseHas('users', [
        'email' => 'john.doe@gmail.com',
    ]);

    $id = User::where('email', 'john.doe@gmail.com')->first()->id;

    $this->assertDatabaseHas('sources', [
        'user_id' => $id,
        'is_default' => true,
    ]);

    $response->assertStatus(201);
});
