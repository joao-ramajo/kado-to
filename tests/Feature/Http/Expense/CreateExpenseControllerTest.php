<?php

declare(strict_types=1);

use App\Models\Source;
use App\Models\User;

test('usuário autenticado cria uma despesa para uma fonte especificada com sucesso', function (): void {
    $user = User::factory()->create();

    $source = Source::factory()->create([
        'user_id' => $user->id,
    ]);
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson(route('api.expenses.create'), [
            'title' => 'Aluguel',
            'amount' => 120000,
            'type' => 'expense',
            'status' => 'pending',
            'source_id' => $source->id,
        ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('expenses', [
        'title' => 'Aluguel',
        'user_id' => $user->id,
    ]);
});

test('usuário autenticado cria uma despesa sem especificar a fonte e a despesa é associada à fonte padrão', function (): void {
    $user = User::factory()->create();

    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson(route('api.expenses.create'), [
            'title' => 'Aluguel',
            'amount' => 120000,
            'type' => 'expense',
            'status' => 'pending',
            'source_id' => null,
        ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('expenses', [
        'title' => 'Aluguel',
        'user_id' => $user->id,
    ]);
});

test('usuário autenticado não consegue criar compra no cartão que excede o limite da fatura', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $creditCard = Source::factory()->creditCard()->create([
        'user_id' => $user->id,
        'credit_limit' => 100000,
        'statement_closing_day' => 5,
        'statement_due_day' => 10,
    ]);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson(route('api.expenses.create'), [
            'title' => 'Compra inicial',
            'amount' => 80000,
            'type' => 'expense',
            'status' => 'pending',
            'source_id' => $creditCard->id,
            'purchase_date' => '2026-03-06',
            'installment_total' => 1,
        ])
        ->assertStatus(201);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson(route('api.expenses.create'), [
            'title' => 'Compra extra',
            'amount' => 30000,
            'type' => 'expense',
            'status' => 'pending',
            'source_id' => $creditCard->id,
            'purchase_date' => '2026-03-06',
            'installment_total' => 1,
        ])
        ->assertStatus(400)
        ->assertJson([
            'message' => 'A compra excede o limite disponível desta fatura.',
        ]);
});
