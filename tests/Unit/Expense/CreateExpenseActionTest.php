<?php

declare(strict_types=1);

use App\Action\Expense\CreateExpense;
use App\Models\CreditCardStatement;
use App\Models\Expense;
use App\Models\Source;
use App\Models\User;
use Illuminate\Support\Facades\Date;

beforeEach(function (): void {
    Date::setTestNow('2026-05-10 10:00:00');
});

afterEach(function (): void {
    Date::setTestNow();
});

test('cria despesa usando a fonte padrão do usuário', function (): void {
    $user = User::factory()->create();

    resolve(CreateExpense::class)->execute([
        'title' => 'Aluguel',
        'amount' => 120000,
        'type' => 'expense',
        'status' => 'pending',
        'userId' => $user->id,
    ]);

    $defaultSourceId = $user->sources()->where('is_default', true)->value('id');

    $this->assertDatabaseHas('expenses', [
        'user_id' => $user->id,
        'source_id' => $defaultSourceId,
        'title' => 'Aluguel',
        'origin_type' => Expense::ORIGIN_DIRECT,
        'occurrence_type' => Expense::OCCURRENCE_DIRECT,
    ]);
});

test('cria despesa paga com payment date implícita', function (): void {
    $user = User::factory()->create();

    resolve(CreateExpense::class)->execute([
        'title' => 'Conta de luz',
        'amount' => 15000,
        'type' => 'expense',
        'status' => 'paid',
        'userId' => $user->id,
    ]);

    $expense = Expense::query()->latest('id')->firstOrFail();

    expect($expense->payment_date?->format('Y-m-d H:i:s'))->toBe('2026-05-10 10:00:00');
});

test('rejeita fonte inexistente para o usuário', function (): void {
    $user = User::factory()->create();

    expect(fn () => resolve(CreateExpense::class)->execute([
        'title' => 'Conta',
        'amount' => 15000,
        'type' => 'expense',
        'status' => 'pending',
        'source_id' => 999999,
        'userId' => $user->id,
    ]))->toThrow(DomainException::class, 'Fonte não encontrada para este usuário.');
});

test('gera parcelas e faturas corretas para compra no cartão', function (): void {
    $user = User::factory()->create();
    $creditCard = Source::factory()->creditCard()->create([
        'user_id' => $user->id,
        'statement_closing_day' => 5,
        'statement_due_day' => 10,
    ]);

    resolve(CreateExpense::class)->execute([
        'title' => 'Notebook',
        'amount' => 100001,
        'type' => 'expense',
        'status' => 'pending',
        'userId' => $user->id,
        'source_id' => $creditCard->id,
        'purchase_date' => '2026-03-06',
        'installment_total' => 3,
    ]);

    $expenses = Expense::query()
        ->where('source_id', $creditCard->id)
        ->orderBy('installment_number')
        ->get();

    expect($expenses)->toHaveCount(3)
        ->and($expenses->pluck('amount')->all())->toBe([333.34, 333.34, 333.33])
        ->and($expenses->pluck('installment_number')->all())->toBe([1, 2, 3]);

    $statements = CreditCardStatement::query()
        ->where('source_id', $creditCard->id)
        ->orderBy('reference_month')
        ->get();

    expect($statements)->toHaveCount(3)
        ->and($statements->pluck('reference_month')->map->format('Y-m-d')->all())->toBe([
            '2026-04-01',
            '2026-05-01',
            '2026-06-01',
        ]);
});

test('bloqueia compra no cartão com tipo inválido ou status pago', function (): void {
    $user = User::factory()->create();
    $creditCard = Source::factory()->creditCard()->create(['user_id' => $user->id]);

    expect(fn () => resolve(CreateExpense::class)->execute([
        'title' => 'Estorno',
        'amount' => 1000,
        'type' => 'income',
        'status' => 'pending',
        'userId' => $user->id,
        'source_id' => $creditCard->id,
    ]))->toThrow(DomainException::class, 'Cartão de crédito aceita apenas despesas.');

    expect(fn () => resolve(CreateExpense::class)->execute([
        'title' => 'Compra',
        'amount' => 1000,
        'type' => 'expense',
        'status' => 'paid',
        'userId' => $user->id,
        'source_id' => $creditCard->id,
    ]))->toThrow(DomainException::class, 'Compras no cartão devem ser quitadas pelo pagamento da fatura.');
});
