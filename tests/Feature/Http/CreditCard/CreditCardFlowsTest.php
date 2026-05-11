<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\CreditCardStatement;
use App\Models\Expense;
use App\Models\Source;
use App\Models\User;

test('deve criar um cartão de crédito com limite e ciclo de fatura', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson(route('api.sources.create'), [
            'name' => 'Visa Black',
            'type' => 'credit_card',
            'color' => '#111827',
            'credit_limit' => 350000,
            'statement_closing_day' => 5,
            'statement_due_day' => 10,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.type', 'credit_card')
        ->assertJsonPath('data.credit_limit', 350000);

    $this->assertDatabaseHas('sources', [
        'user_id' => $user->id,
        'name' => 'Visa Black',
        'type' => 'credit_card',
        'credit_limit' => 350000,
        'statement_closing_day' => 5,
        'statement_due_day' => 10,
    ]);
});

test('deve gerar parcelas e faturas corretas para compra parcelada no cartão', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $categoryId = Category::factory()->create(['user_id' => $user->id])->id;
    $creditCard = Source::factory()->creditCard()->create([
        'user_id' => $user->id,
        'name' => 'Mastercard',
        'statement_closing_day' => 5,
        'statement_due_day' => 10,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson(route('api.expenses.create'), [
            'title' => 'Notebook',
            'amount' => 300000,
            'type' => 'expense',
            'status' => 'pending',
            'source_id' => $creditCard->id,
            'purchase_date' => '2026-03-06',
            'installment_total' => 3,
            'category_id' => $categoryId,
        ]);

    $response->assertCreated();

    $expenses = Expense::where('user_id', $user->id)
        ->where('source_id', $creditCard->id)
        ->orderBy('installment_number')
        ->get();

    expect($expenses)->toHaveCount(3);
    expect($expenses->pluck('amount')->all())->toBe([1000.0, 1000.0, 1000.0]);
    expect($expenses->pluck('installment_number')->all())->toBe([1, 2, 3]);
    expect($expenses->pluck('status')->unique()->all())->toBe(['pending']);

    $statements = CreditCardStatement::where('source_id', $creditCard->id)
        ->orderBy('reference_month')
        ->get();

    expect($statements)->toHaveCount(3);
    expect($statements->pluck('reference_month')->map->format('Y-m-d')->all())->toBe([
        '2026-04-01',
        '2026-05-01',
        '2026-06-01',
    ]);
});

test('compra no cartão nao deve afetar o caixa principal antes do pagamento da fatura', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $defaultSourceId = $user->sources()->where('is_default', true)->value('id');
    $creditCard = Source::factory()->creditCard()->create([
        'user_id' => $user->id,
        'credit_limit' => 200000,
    ]);

    Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $defaultSourceId,
        'type' => 'income',
        'status' => 'paid',
        'amount' => 500000,
    ]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson(route('api.expenses.create'), [
            'title' => 'Celular',
            'amount' => 120000,
            'type' => 'expense',
            'status' => 'pending',
            'source_id' => $creditCard->id,
            'purchase_date' => '2026-03-01',
            'installment_total' => 1,
        ])
        ->assertCreated();

    $summary = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson(route('api.get-summary'));

    $summary->assertOk()
        ->assertJson([
            'total_receive' => 500000,
            'total_expense' => 0,
            'expected_total' => 500000,
            'credit_card_open_total' => 120000,
            'credit_card_limit_used' => 120000,
        ]);
});

test('deve pagar a fatura integralmente e baixar o caixa', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $defaultSource = $user->sources()->where('is_default', true)->firstOrFail();
    $creditCard = Source::factory()->creditCard()->create([
        'user_id' => $user->id,
        'name' => 'Nubank',
    ]);

    Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $defaultSource->id,
        'type' => 'income',
        'status' => 'paid',
        'amount' => 400000,
    ]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson(route('api.expenses.create'), [
            'title' => 'Mesa',
            'amount' => 90000,
            'type' => 'expense',
            'status' => 'pending',
            'source_id' => $creditCard->id,
            'purchase_date' => '2026-03-01',
            'installment_total' => 1,
        ])
        ->assertCreated();

    $statement = CreditCardStatement::where('source_id', $creditCard->id)->firstOrFail();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson(route('api.credit-cards.statements.pay', ['statementId' => $statement->id]), [
            'payment_source_id' => $defaultSource->id,
        ])
        ->assertOk();

    $statement->refresh();
    expect($statement->status)->toBe('paid');

    $purchase = Expense::where('credit_card_statement_id', $statement->id)->firstOrFail();
    expect($purchase->status)->toBe('paid');

    $this->assertDatabaseHas('expenses', [
        'user_id' => $user->id,
        'source_id' => $defaultSource->id,
        'occurrence_type' => 'invoice_payment',
        'amount' => 90000,
        'status' => 'paid',
    ]);

    $summary = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson(route('api.get-summary'));

    $summary->assertOk()
        ->assertJson([
            'total_receive' => 400000,
            'total_expense' => 90000,
            'expected_total' => 310000,
            'credit_card_open_total' => 0,
            'credit_card_limit_used' => 0,
        ]);
});

test('nao deve permitir marcar compra de cartão como paga manualmente', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $creditCard = Source::factory()->creditCard()->create([
        'user_id' => $user->id,
    ]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson(route('api.expenses.create'), [
            'title' => 'Curso',
            'amount' => 150000,
            'type' => 'expense',
            'status' => 'pending',
            'source_id' => $creditCard->id,
            'purchase_date' => '2026-03-01',
            'installment_total' => 1,
        ])
        ->assertCreated();

    $expense = Expense::where('source_id', $creditCard->id)->firstOrFail();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson(route('api.expenses.mark-as-paid', ['id' => $expense->id]))
        ->assertStatus(400)
        ->assertJson([
            'message' => 'Compras no cartão devem ser quitadas pelo pagamento da fatura.',
        ]);
});

test('nao deve permitir pagar fatura usando outro cartão', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $creditCard = Source::factory()->creditCard()->create([
        'user_id' => $user->id,
        'name' => 'Visa',
    ]);
    $otherCreditCard = Source::factory()->creditCard()->create([
        'user_id' => $user->id,
        'name' => 'Amex',
    ]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson(route('api.expenses.create'), [
            'title' => 'TV',
            'amount' => 230000,
            'type' => 'expense',
            'status' => 'pending',
            'source_id' => $creditCard->id,
            'purchase_date' => '2026-03-01',
            'installment_total' => 1,
        ])
        ->assertCreated();

    $statement = CreditCardStatement::where('source_id', $creditCard->id)->firstOrFail();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson(route('api.credit-cards.statements.pay', ['statementId' => $statement->id]), [
            'payment_source_id' => $otherCreditCard->id,
        ])
        ->assertStatus(400)
        ->assertJson([
            'message' => 'A fatura deve ser paga com uma fonte de caixa.',
        ]);
});
