<?php

declare(strict_types=1);

use App\Models\CreditCardStatement;
use App\Models\Expense;
use App\Models\Source;
use App\Models\User;

test('login retorna erro para credenciais inválidas', function (): void {
    $user = User::factory()->create([
        'email' => 'john@example.com',
        'password' => bcrypt('secret-123'),
    ]);

    $response = $this->postJson(route('api.login'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(400)
        ->assertJson(['message' => 'Credenciais inválidas.']);
});

test('register valida termos e confirmação de senha', function (): void {
    $response = $this->postJson(route('api.register'), [
        'name' => 'John Doe',
        'email' => 'john.doe@gmail.com',
        'password' => 'password',
        'password_confirmation' => 'different',
        'terms' => false,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password', 'terms']);
});

test('create source valida campos obrigatórios para cartão', function (): void {
    $user = User::factory()->create();
    $token = apiTokenFor($user);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson(route('api.sources.create'), [
            'name' => 'Visa',
            'type' => 'credit_card',
            'color' => '#000000',
            'statement_closing_day' => 10,
            'statement_due_day' => 9,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['credit_limit', 'statement_due_day']);
});

test('create category e pay statement validam payload obrigatório', function (): void {
    $user = User::factory()->create();
    $token = apiTokenFor($user);
    $creditCard = Source::factory()->creditCard()->create(['user_id' => $user->id]);
    $statement = CreditCardStatement::factory()->create([
        'source_id' => $creditCard->id,
        'total_amount' => 10000,
    ]);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson(route('api.categories.create'), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'color']);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson(route('api.credit-cards.statements.pay', ['statementId' => $statement->id]), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['payment_source_id']);
});

test('rotas de exportação csv e xlsx retornam arquivos', function (): void {
    $user = User::factory()->create();
    $token = apiTokenFor($user);
    $sourceId = $user->sources()->where('is_default', true)->value('id');

    Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $sourceId,
        'title' => 'Mercado',
    ]);

    $csvResponse = $this->withHeader('Authorization', 'Bearer '.$token)
        ->get(route('api.csv.export'));

    $csvResponse->assertOk();

    expect($csvResponse->headers->get('content-type'))->toContain('text/csv');

    $xlsxResponse = $this->withHeader('Authorization', 'Bearer '.$token)
        ->get(route('api.xlsx.export'));

    $xlsxResponse->assertOk();

    expect($xlsxResponse->headers->get('content-type'))->toContain('spreadsheetml');
});

test('rota web raiz responde mensagem de boas-vindas', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertJson([
            'message' => 'Bem vindo ao Koda API',
        ]);
});
