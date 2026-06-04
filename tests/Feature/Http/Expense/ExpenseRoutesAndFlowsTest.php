<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\CreditCardStatement;
use App\Models\Expense;
use App\Models\Source;
use App\Models\User;
use Illuminate\Support\Facades\Date;

test('quero criar uma despesa com sucesso em uma fonte secundaria', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $secondarySource = Source::factory()->create([
        'user_id' => $user->id,
        'is_default' => false,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson(route('api.expenses.create'), [
            'title' => 'Internet',
            'amount' => 9900,
            'type' => 'expense',
            'status' => 'pending',
            'source_id' => $secondarySource->id,
        ]);

    $response->assertCreated();

    $this->assertDatabaseHas('expenses', [
        'title' => 'Internet',
        'user_id' => $user->id,
        'source_id' => $secondarySource->id,
    ]);
});

test('quero listar minhas todas as minhas despesas com sucesso', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $defaultSourceId = $user->sources()->where('is_default', true)->value('id');

    Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $defaultSourceId,
        'status' => 'paid',
    ]);
    Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $defaultSourceId,
        'status' => 'pending',
    ]);
    Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $defaultSourceId,
        'status' => 'overdue',
    ]);

    $otherUser = User::factory()->create();
    $otherSourceId = $otherUser->sources()->where('is_default', true)->value('id');
    Expense::factory()->create([
        'user_id' => $otherUser->id,
        'source_id' => $otherSourceId,
        'status' => 'paid',
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson(route('api.get-expenses'));

    $response->assertOk()
        ->assertJsonCount(3);
});

test('quero listar apenas as despesas pagas com sucesso', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $defaultSourceId = $user->sources()->where('is_default', true)->value('id');

    Expense::factory()->count(2)->create([
        'user_id' => $user->id,
        'source_id' => $defaultSourceId,
        'status' => 'paid',
    ]);
    Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $defaultSourceId,
        'status' => 'pending',
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson(route('api.get-expenses', ['status' => 'paid']));

    $response->assertOk()
        ->assertJsonCount(2);

    $statuses = collect($response->json())->pluck('status')->unique()->values()->all();
    expect($statuses)->toBe(['paid']);
});

test('na listagem de despesas devo conseguir filtrar por categoria e mes', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $defaultSourceId = $user->sources()->where('is_default', true)->value('id');
    $categoryA = Category::factory()->create(['user_id' => $user->id]);
    $categoryB = Category::factory()->create(['user_id' => $user->id]);

    $paidInMonth = Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $defaultSourceId,
        'category_id' => $categoryA->id,
        'status' => 'paid',
        'payment_date' => '2026-01-20 10:00:00',
        'created_at' => '2026-02-10 10:00:00',
        'updated_at' => '2026-02-10 10:00:00',
    ]);

    $pendingCreatedInMonth = Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $defaultSourceId,
        'category_id' => $categoryA->id,
        'status' => 'pending',
        'payment_date' => null,
        'created_at' => '2026-01-10 10:00:00',
        'updated_at' => '2026-01-10 10:00:00',
    ]);

    Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $defaultSourceId,
        'status' => 'paid',
        'payment_date' => '2026-02-10 10:00:00',
        'category_id' => $categoryB->id,
        'created_at' => '2026-01-12 10:00:00',
        'updated_at' => '2026-01-12 10:00:00',
    ]);

    Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $defaultSourceId,
        'category_id' => $categoryA->id,
        'status' => 'paid',
        'payment_date' => '2026-02-12 10:00:00',
        'created_at' => '2026-01-12 10:00:00',
        'updated_at' => '2026-01-12 10:00:00',
    ]);

    Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $defaultSourceId,
        'category_id' => $categoryA->id,
        'status' => 'pending',
        'payment_date' => null,
        'created_at' => '2026-02-13 10:00:00',
        'updated_at' => '2026-02-13 10:00:00',
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson(route('api.get-expenses', [
            'category_id' => $categoryA->id,
            'month' => 1,
        ]));

    $response->assertOk()
        ->assertJsonCount(2);

    $ids = collect($response->json())->pluck('id')->all();
    expect($ids)->toContain($paidInMonth->id);
    expect($ids)->toContain($pendingCreatedInMonth->id);
});

test('na listagem de despesas devo conseguir filtrar por fonte do usuario', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $defaultSourceId = $user->sources()->where('is_default', true)->value('id');
    $secondarySource = Source::factory()->create([
        'user_id' => $user->id,
        'is_default' => false,
    ]);

    $expectedExpense = Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $secondarySource->id,
    ]);

    Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $defaultSourceId,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson(route('api.get-expenses', ['source_id' => $secondarySource->id]));

    $response->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $expectedExpense->id);
});

test('na listagem de despesas devo rejeitar fonte que nao pertence ao usuario', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $otherUser = User::factory()->create();
    $otherSourceId = $otherUser->sources()->where('is_default', true)->value('id');

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson(route('api.get-expenses', ['source_id' => $otherSourceId]));

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['source_id']);
});

test('quero lsitar todas as despesas pendentes', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $defaultSourceId = $user->sources()->where('is_default', true)->value('id');

    Expense::factory()->count(2)->create([
        'user_id' => $user->id,
        'source_id' => $defaultSourceId,
        'status' => 'pending',
    ]);
    Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $defaultSourceId,
        'status' => 'paid',
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson(route('api.get-expenses', ['status' => 'pending']));

    $response->assertOk()
        ->assertJsonCount(2);

    $statuses = collect($response->json())->pluck('status')->unique()->values()->all();
    expect($statuses)->toBe(['pending']);
});

test('na listagem de despesas devo conseguir buscar por query ignorando maiusculas minusculas e pontuacao', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $defaultSourceId = $user->sources()->where('is_default', true)->value('id');

    Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $defaultSourceId,
        'title' => 'Uber - Corrida Centro',
    ]);
    Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $defaultSourceId,
        'title' => 'U.B.E.R* aeroporto',
    ]);
    Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $defaultSourceId,
        'title' => '99 Taxi',
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson(route('api.get-expenses', ['query' => 'uber']));

    $response->assertOk()
        ->assertJsonCount(2);
});

test('na listagem de despesas devo conseguir buscar por query ignorando acentos', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $defaultSourceId = $user->sources()->where('is_default', true)->value('id');

    Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $defaultSourceId,
        'title' => 'Compra de Maçã',
    ]);
    Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $defaultSourceId,
        'title' => 'Pão frances',
    ]);
    Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $defaultSourceId,
        'title' => 'Mercado',
    ]);

    $responseMaca = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson(route('api.get-expenses', ['query' => 'maca']));
    $responseMaca->assertOk()
        ->assertJsonCount(1);

    $responsePao = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson(route('api.get-expenses', ['query' => 'pao']));
    $responsePao->assertOk()
        ->assertJsonCount(1);
});

test('quero editar uma despesa com sucesso', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $defaultSourceId = $user->sources()->where('is_default', true)->value('id');

    $expense = Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $defaultSourceId,
        'title' => 'Conta antiga',
        'amount' => 1000,
        'type' => 'expense',
        'status' => 'pending',
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->putJson(route('api.expenses.update', ['id' => $expense->id]), [
            'title' => 'Conta atualizada',
            'amount' => 2500,
            'type' => 'expense',
            'status' => 'pending',
            'source_id' => $defaultSourceId,
        ]);

    $response->assertOk();

    $this->assertDatabaseHas('expenses', [
        'id' => $expense->id,
        'title' => 'Conta atualizada',
        'amount' => 2500,
        'type' => 'expense',
    ]);
});

test('quero editar uma despesa para uma entrada com sucesso', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $defaultSourceId = $user->sources()->where('is_default', true)->value('id');

    $expense = Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $defaultSourceId,
        'type' => 'expense',
        'status' => 'pending',
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->putJson(route('api.expenses.update', ['id' => $expense->id]), [
            'title' => 'Salario freelance',
            'amount' => 450000,
            'type' => 'income',
            'status' => 'paid',
            'source_id' => $defaultSourceId,
        ]);

    $response->assertOk();

    $this->assertDatabaseHas('expenses', [
        'id' => $expense->id,
        'type' => 'income',
        'amount' => 450000,
    ]);
});

test('quero editar uma compra no cartao de credito e sincronizar a fatura', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $creditCard = Source::factory()->creditCard()->create([
        'user_id' => $user->id,
        'statement_closing_day' => 5,
        'statement_due_day' => 10,
    ]);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson(route('api.expenses.create'), [
            'title' => 'Notebook',
            'amount' => 100000,
            'type' => 'expense',
            'status' => 'pending',
            'source_id' => $creditCard->id,
            'purchase_date' => '2026-03-01',
            'installment_total' => 1,
        ])
        ->assertCreated();

    $expense = Expense::query()
        ->where('user_id', $user->id)
        ->where('source_id', $creditCard->id)
        ->firstOrFail();

    $statement = CreditCardStatement::query()
        ->where('source_id', $creditCard->id)
        ->firstOrFail();

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->putJson(route('api.expenses.update', ['id' => $expense->id]), [
            'title' => 'Notebook gamer',
            'amount' => 125000,
            'type' => 'expense',
            'status' => 'pending',
            'source_id' => $creditCard->id,
        ]);

    $response->assertOk();

    $expense->refresh();
    $statement->refresh();

    expect($expense->title)->toBe('Notebook gamer');
    expect($expense->amount)->toBe(1250.0);
    expect($statement->total_amount)->toBe(125000);
});

test('quero impedir que uma compra no cartao ultrapasse o limite da fatura na edicao', function (): void {
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
            'title' => 'Notebook',
            'amount' => 80000,
            'type' => 'expense',
            'status' => 'pending',
            'source_id' => $creditCard->id,
            'purchase_date' => '2026-03-01',
            'installment_total' => 1,
        ])
        ->assertCreated();

    $expense = Expense::query()
        ->where('user_id', $user->id)
        ->where('source_id', $creditCard->id)
        ->firstOrFail();

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->putJson(route('api.expenses.update', ['id' => $expense->id]), [
            'title' => 'Notebook gamer',
            'amount' => 110000,
            'type' => 'expense',
            'status' => 'pending',
            'source_id' => $creditCard->id,
        ]);

    $response->assertStatus(400)
        ->assertJson([
            'message' => 'A compra excede o limite disponível desta fatura.',
        ]);
});

test('quero editar uma compra no cartao reduzindo o valor e sincronizar a fatura', function (): void {
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
            'title' => 'Notebook',
            'amount' => 80000,
            'type' => 'expense',
            'status' => 'pending',
            'source_id' => $creditCard->id,
            'purchase_date' => '2026-03-01',
            'installment_total' => 1,
        ])
        ->assertCreated();

    $expense = Expense::query()
        ->where('user_id', $user->id)
        ->where('source_id', $creditCard->id)
        ->firstOrFail();

    $statement = CreditCardStatement::query()
        ->where('source_id', $creditCard->id)
        ->firstOrFail();

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->putJson(route('api.expenses.update', ['id' => $expense->id]), [
            'title' => 'Notebook gamer',
            'amount' => 50000,
            'type' => 'expense',
            'status' => 'pending',
            'source_id' => $creditCard->id,
        ]);

    $response->assertOk();

    $expense->refresh();
    $statement->refresh();

    expect($expense->amount)->toBe(500.0);
    expect($statement->total_amount)->toBe(50000);
});

test('ao editar uma despesa paga devo conseguir informar payment_date somente com data', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $defaultSourceId = $user->sources()->where('is_default', true)->value('id');
    $paymentDate = '2026-03-02';

    $expense = Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $defaultSourceId,
        'status' => 'pending',
        'payment_date' => null,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->putJson(route('api.expenses.update', ['id' => $expense->id]), [
            'title' => 'Parcela cartao',
            'amount' => 15000,
            'type' => 'expense',
            'status' => 'paid',
            'source_id' => $defaultSourceId,
            'payment_date' => $paymentDate,
        ]);

    $response->assertOk();

    $expense->refresh();
    expect($expense->payment_date?->format('Y-m-d H:i:s'))->toBe('2026-03-02 00:00:00');
});

test('ao editar uma despesa paga com payment_date invalido deve retornar erro de validacao', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $defaultSourceId = $user->sources()->where('is_default', true)->value('id');

    $expense = Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $defaultSourceId,
        'status' => 'pending',
        'payment_date' => null,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->putJson(route('api.expenses.update', ['id' => $expense->id]), [
            'title' => 'Conta internet',
            'amount' => 12000,
            'type' => 'expense',
            'status' => 'paid',
            'source_id' => $defaultSourceId,
            'payment_date' => '2026-02-30',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['payment_date']);
});

test('ao editar uma despesa paga com payment_date no futuro deve retornar erro de validacao', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $defaultSourceId = $user->sources()->where('is_default', true)->value('id');
    $futureDate = now()->addDay()->format('Y-m-d');

    $expense = Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $defaultSourceId,
        'status' => 'pending',
        'payment_date' => null,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->putJson(route('api.expenses.update', ['id' => $expense->id]), [
            'title' => 'Conta energia',
            'amount' => 18000,
            'type' => 'expense',
            'status' => 'paid',
            'source_id' => $defaultSourceId,
            'payment_date' => $futureDate,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['payment_date']);
});

test('quero ver meus resumos gerais com o total recebido, total gasto e saldo esperado com sucesso', function (): void {
    Date::setTestNow('2026-05-14 10:00:00');

    try {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        $defaultSourceId = $user->sources()->where('is_default', true)->value('id');
        $secondarySourceId = Source::factory()->create([
            'user_id' => $user->id,
            'is_default' => false,
        ])->id;

        Expense::factory()->create([
            'user_id' => $user->id,
            'source_id' => $defaultSourceId,
            'type' => 'income',
            'status' => 'paid',
            'amount' => 10000,
        ]);
        Expense::factory()->create([
            'user_id' => $user->id,
            'source_id' => $defaultSourceId,
            'type' => 'expense',
            'status' => 'paid',
            'amount' => 2500,
        ]);
        Expense::factory()->create([
            'user_id' => $user->id,
            'source_id' => $defaultSourceId,
            'type' => 'income',
            'status' => 'pending',
            'amount' => 1500,
        ]);
        Expense::factory()->create([
            'user_id' => $user->id,
            'source_id' => $defaultSourceId,
            'type' => 'expense',
            'status' => 'pending',
            'amount' => 700,
        ]);

        Expense::factory()->create([
            'user_id' => $user->id,
            'source_id' => $secondarySourceId,
            'type' => 'expense',
            'status' => 'paid',
            'amount' => 999999,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson(route('api.get-summary'));

        $response->assertOk()
            ->assertJson([
                'total_receive' => 10000,
                'total_expense' => 2500,
                'expected_total' => 8300,
                'total_receive_30_days' => 10000,
                'total_expense_30_days' => 2500,
            ]);
    } finally {
        Date::setTestNow();
    }
});

test('uma nova despesa na fonte principal deve alterar o valor corretamente para o saldo esperado', function (): void {
    Date::setTestNow('2026-05-14 10:00:00');

    try {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        $defaultSource = $user->sources()->where('is_default', true)->firstOrFail();

        Expense::factory()->create([
            'user_id' => $user->id,
            'source_id' => $defaultSource->id,
            'type' => 'income',
            'status' => 'paid',
            'amount' => 8000,
        ]);

        $before = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson(route('api.get-summary'));

        $before->assertOk()
            ->assertJson(['expected_total' => 8000]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson(route('api.expenses.create'), [
                'title' => 'Conta energia',
                'amount' => 1200,
                'type' => 'expense',
                'status' => 'pending',
                'source_id' => $defaultSource->id,
            ])
            ->assertCreated();

        $after = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson(route('api.get-summary'));

        $after->assertOk()
            ->assertJson([
                'expected_total' => 6800,
                'total_receive_30_days' => 8000,
                'total_expense_30_days' => 0,
            ]);
    } finally {
        Date::setTestNow();
    }
});

test('adicionar um novo gasto deve alterar o total gasto', function (): void {
    Date::setTestNow('2026-05-14 10:00:00');

    try {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        $defaultSource = $user->sources()->where('is_default', true)->firstOrFail();

        $before = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson(route('api.get-summary'));

        $before->assertOk()
            ->assertJson(['total_expense' => 0]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson(route('api.expenses.create'), [
                'title' => 'Mercado',
                'amount' => 3200,
                'type' => 'expense',
                'status' => 'paid',
                'source_id' => $defaultSource->id,
            ])
            ->assertCreated();

        $after = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson(route('api.get-summary'));

        $after->assertOk()
            ->assertJson([
                'total_expense' => 3200,
                'total_receive_30_days' => 0,
                'total_expense_30_days' => 3200,
            ]);
    } finally {
        Date::setTestNow();
    }
});

test('ao adicionar despesas a uma categoria especifica, a contagem de despesas nela deve ser incrementada com sucesso', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $defaultSourceId = $user->sources()->where('is_default', true)->value('id');
    $category = Category::factory()->create([
        'user_id' => $user->id,
    ]);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson(route('api.expenses.create'), [
            'title' => 'Despesa 1',
            'amount' => 1000,
            'type' => 'expense',
            'status' => 'pending',
            'source_id' => $defaultSourceId,
            'category_id' => $category->id,
        ])
        ->assertCreated();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson(route('api.expenses.create'), [
            'title' => 'Despesa 2',
            'amount' => 2000,
            'type' => 'expense',
            'status' => 'pending',
            'source_id' => $defaultSourceId,
            'category_id' => $category->id,
        ])
        ->assertCreated();

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson(route('api.categories.list'));

    $selected = collect($response->json())->firstWhere('id', $category->id);
    expect($selected)->not->toBeNull();
    expect($selected['expenses_count'])->toBe(2);
});

test('ao apagar uma despesa a categoria deve ser decrementada a quantidade de despesas com sucesso', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $defaultSourceId = $user->sources()->where('is_default', true)->value('id');
    $category = Category::factory()->create([
        'user_id' => $user->id,
    ]);

    $expense = Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $defaultSourceId,
        'category_id' => $category->id,
        'type' => 'expense',
    ]);

    $before = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson(route('api.categories.list'));
    $beforeCategory = collect($before->json())->firstWhere('id', $category->id);
    expect($beforeCategory['expenses_count'])->toBe(1);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->deleteJson(route('api.expenses.delete', ['id' => $expense->id]))
        ->assertOk();

    $after = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson(route('api.categories.list'));
    $afterCategory = collect($after->json())->firstWhere('id', $category->id);
    expect($afterCategory['expenses_count'])->toBe(0);
});

test('na listagem de categorias devo conseguir filtrar por mes via query param', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $defaultSourceId = $user->sources()->where('is_default', true)->value('id');
    $category = Category::factory()->create([
        'user_id' => $user->id,
    ]);

    Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $defaultSourceId,
        'category_id' => $category->id,
        'type' => 'expense',
        'amount' => 1000,
        'created_at' => '2026-01-10 10:00:00',
        'updated_at' => '2026-01-10 10:00:00',
    ]);

    Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $defaultSourceId,
        'category_id' => $category->id,
        'type' => 'income',
        'amount' => 3000,
        'created_at' => '2026-01-11 10:00:00',
        'updated_at' => '2026-01-11 10:00:00',
    ]);

    Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $defaultSourceId,
        'category_id' => $category->id,
        'type' => 'expense',
        'amount' => 7000,
        'created_at' => '2026-02-10 10:00:00',
        'updated_at' => '2026-02-10 10:00:00',
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson(route('api.categories.list', ['month' => 1]));

    $response->assertOk();

    $selected = collect($response->json())->firstWhere('id', $category->id);

    expect($selected)->not->toBeNull();
    expect($selected['expenses_count'])->toBe(2);
    expect($selected['expenses_total_amount'])->toBe(1000);
});

test('ao filtrar categorias por mes invalido deve retornar erro de validacao', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson(route('api.categories.list', ['month' => 13]));

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['month']);
});

test('quero editar uma categoria com sucesso alterando apenas nome e cor', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $category = Category::factory()->create([
        'user_id' => $user->id,
        'name' => 'Lazer',
        'color' => '#111111',
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->putJson(route('api.categories.update', ['id' => $category->id]), [
            'name' => 'Lazer e viagens',
            'color' => '#22c55e',
        ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Categoria atualizada com sucesso.',
        ]);

    $this->assertDatabaseHas('categories', [
        'id' => $category->id,
        'name' => 'Lazer e viagens',
        'color' => '#22c55e',
    ]);
});

test('na edicao de categoria deve retornar erro de validacao para payload invalido', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $category = Category::factory()->create([
        'user_id' => $user->id,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->putJson(route('api.categories.update', ['id' => $category->id]), [
            'name' => '',
            'color' => '#fff',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'color']);
});

test('na edicao de categoria nao devo conseguir usar um nome ja existente para o mesmo usuario', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    Category::factory()->create([
        'user_id' => $user->id,
        'name' => 'Transporte',
    ]);

    $categoryToUpdate = Category::factory()->create([
        'user_id' => $user->id,
        'name' => 'Lazer',
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->putJson(route('api.categories.update', ['id' => $categoryToUpdate->id]), [
            'name' => 'Transporte',
            'color' => '#3b82f6',
        ]);

    $response->assertStatus(400)
        ->assertJson([
            'message' => 'Categoria já registrada.',
        ]);
});

test('na edicao de categoria nao devo conseguir alterar categoria de outro usuario', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $otherUser = User::factory()->create();

    $category = Category::factory()->create([
        'user_id' => $otherUser->id,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->putJson(route('api.categories.update', ['id' => $category->id]), [
            'name' => 'Tentativa',
            'color' => '#ef4444',
        ]);

    $response->assertStatus(400)
        ->assertJson([
            'message' => 'Você não pode alterar esta categoria.',
        ]);
});

test('nas fontes devo conseguir ver corretamente os valores de total recebido, total gasto e saldo final e a quantidade de registros nela', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $defaultSource = $user->sources()->where('is_default', true)->firstOrFail();
    $secondarySource = Source::factory()->create([
        'user_id' => $user->id,
        'is_default' => false,
    ]);

    Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $defaultSource->id,
        'type' => 'income',
        'amount' => 10000,
    ]);
    Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $defaultSource->id,
        'type' => 'expense',
        'amount' => 4000,
    ]);

    Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $secondarySource->id,
        'type' => 'income',
        'amount' => 7000,
    ]);
    Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $secondarySource->id,
        'type' => 'expense',
        'amount' => 1500,
    ]);
    Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $secondarySource->id,
        'type' => 'expense',
        'amount' => 500,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson(route('api.sources.details'));

    $response->assertOk();

    $items = collect($response->json());

    $default = $items->firstWhere('id', $defaultSource->id);
    $secondary = $items->firstWhere('id', $secondarySource->id);

    expect($default)->not->toBeNull();
    expect($default['total_income'])->toBe(10000);
    expect($default['total_expense'])->toBe(4000);
    expect($default['balance'])->toBe(6000);
    expect($default['expenses_count'])->toBe(2);

    expect($secondary)->not->toBeNull();
    expect($secondary['total_income'])->toBe(7000);
    expect($secondary['total_expense'])->toBe(2000);
    expect($secondary['balance'])->toBe(5000);
    expect($secondary['expenses_count'])->toBe(3);
});

test('devo conseguir marcar uma despesa como paga somente se ela estiver pendente ou atrasada', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $defaultSourceId = $user->sources()->where('is_default', true)->value('id');

    $expense = Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $defaultSourceId,
        'status' => 'paid',
        'payment_date' => now()->subDay(),
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson(route('api.expenses.mark-as-paid', ['id' => $expense->id]));

    $response->assertStatus(400)
        ->assertJson([
            'message' => 'Apenas despesas pendentes ou atrasadas podem ser marcadas como pagas.',
        ]);
});

test('marcar uma despesa como paga deve adicionar a data de pagamento como a data atual no momento em que marquei', function (): void {
    $now = Date::create(2026, 2, 15, 10, 30, 0);
    Date::setTestNow($now);

    try {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        $defaultSourceId = $user->sources()->where('is_default', true)->value('id');

        $expense = Expense::factory()->create([
            'user_id' => $user->id,
            'source_id' => $defaultSourceId,
            'status' => 'overdue',
            'payment_date' => null,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson(route('api.expenses.mark-as-paid', ['id' => $expense->id]))
            ->assertOk();

        $expense->refresh();
        expect($expense->status)->toBe('paid');
        expect($expense->payment_date?->format('Y-m-d H:i:s'))->toBe($now->format('Y-m-d H:i:s'));
    } finally {
        Date::setTestNow();
    }
});

test('devo conseguir excluir uma despesa com sucesso', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $defaultSourceId = $user->sources()->where('is_default', true)->value('id');

    $expense = Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $defaultSourceId,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->deleteJson(route('api.expenses.delete', ['id' => $expense->id]));

    $response->assertOk();

    $this->assertDatabaseMissing('expenses', [
        'id' => $expense->id,
    ]);
});

test('ao criar uma despesa paga devo conseguir informar payment_date somente com data', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $defaultSourceId = $user->sources()->where('is_default', true)->value('id');
    $paymentDate = '2026-03-02';

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson(route('api.expenses.create'), [
            'title' => 'Parcela cartao',
            'amount' => 15000,
            'type' => 'expense',
            'status' => 'paid',
            'source_id' => $defaultSourceId,
            'payment_date' => $paymentDate,
        ]);

    $response->assertCreated();

    $expense = Expense::query()
        ->where('user_id', $user->id)
        ->where('title', 'Parcela cartao')
        ->firstOrFail();

    expect($expense->payment_date?->format('Y-m-d H:i:s'))->toBe('2026-03-02 00:00:00');
});

test('ao criar uma despesa paga com payment_date invalido deve retornar erro de validacao', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $defaultSourceId = $user->sources()->where('is_default', true)->value('id');

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson(route('api.expenses.create'), [
            'title' => 'Conta internet',
            'amount' => 12000,
            'type' => 'expense',
            'status' => 'paid',
            'source_id' => $defaultSourceId,
            'payment_date' => '2026-02-30',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['payment_date']);
});

test('ao criar uma despesa paga com payment_date no futuro deve retornar erro de validacao', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $defaultSourceId = $user->sources()->where('is_default', true)->value('id');
    $futureDate = now()->addDay()->format('Y-m-d');

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson(route('api.expenses.create'), [
            'title' => 'Conta energia',
            'amount' => 18000,
            'type' => 'expense',
            'status' => 'paid',
            'source_id' => $defaultSourceId,
            'payment_date' => $futureDate,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['payment_date']);
});
