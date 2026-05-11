<?php

declare(strict_types=1);

use App\Action\CreditCard\PayCreditCardStatementAction;
use App\Models\CreditCardStatement;
use App\Models\Expense;
use App\Models\Source;
use App\Models\User;
use App\Support\CreditCard\CreditCardStatementService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;

beforeEach(function (): void {
    Date::setTestNow('2026-05-10 10:00:00');
});

afterEach(function (): void {
    Date::setTestNow();
});

test('split installments distribui resto corretamente', function (): void {
    expect(resolve(CreditCardStatementService::class)->splitInstallments(100001, 3))
        ->toBe([33334, 33334, 33333]);
});

test('split installments rejeita quantidade inválida', function (): void {
    expect(fn () => resolve(CreditCardStatementService::class)->splitInstallments(1000, 0))
        ->toThrow(DomainException::class, 'A quantidade de parcelas deve ser maior que zero.');
});

test('resolve statement calcula reference month, closing e due dates', function (): void {
    $user = User::factory()->create();
    $creditCard = Source::factory()->creditCard()->create([
        'user_id' => $user->id,
        'statement_closing_day' => 5,
        'statement_due_day' => 10,
    ]);

    $statement = resolve(CreditCardStatementService::class)->resolveForInstallment(
        $creditCard,
        CarbonImmutable::parse('2026-03-06')
    );

    expect($statement->reference_month->format('Y-m-d'))->toBe('2026-04-01')
        ->and($statement->closing_at->format('Y-m-d'))->toBe('2026-04-05')
        ->and($statement->due_at->format('Y-m-d'))->toBe('2026-04-10')
        ->and($statement->status)->toBe(CreditCardStatement::STATUS_CLOSED);
});

test('sync soma apenas compras vinculadas à fatura', function (): void {
    $user = User::factory()->create();
    $creditCard = Source::factory()->creditCard()->create(['user_id' => $user->id]);
    $statement = CreditCardStatement::factory()->create([
        'source_id' => $creditCard->id,
        'status' => CreditCardStatement::STATUS_OPEN,
        'total_amount' => 0,
    ]);

    Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $creditCard->id,
        'amount' => 10000,
        'origin_type' => Expense::ORIGIN_CREDIT_CARD,
        'occurrence_type' => Expense::OCCURRENCE_PURCHASE,
        'credit_card_statement_id' => $statement->id,
    ]);
    Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $creditCard->id,
        'amount' => 5000,
        'origin_type' => Expense::ORIGIN_DIRECT,
        'occurrence_type' => Expense::OCCURRENCE_DIRECT,
        'credit_card_statement_id' => $statement->id,
    ]);

    resolve(CreditCardStatementService::class)->syncById($statement->id);

    expect($statement->refresh()->total_amount)->toBe(10000);
});

test('pay statement baixa compras e cria lançamento no caixa', function (): void {
    $user = User::factory()->create();
    $cashSource = $user->sources()->where('is_default', true)->firstOrFail();
    $creditCard = Source::factory()->creditCard()->create(['user_id' => $user->id]);
    $statement = CreditCardStatement::factory()->create([
        'source_id' => $creditCard->id,
        'status' => CreditCardStatement::STATUS_OPEN,
        'total_amount' => 30000,
    ]);

    Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $creditCard->id,
        'amount' => 30000,
        'status' => 'pending',
        'origin_type' => Expense::ORIGIN_CREDIT_CARD,
        'occurrence_type' => Expense::OCCURRENCE_PURCHASE,
        'credit_card_statement_id' => $statement->id,
    ]);

    resolve(PayCreditCardStatementAction::class)->execute($statement->id, $user->id, $cashSource->id);

    expect($statement->refresh()->status)->toBe(CreditCardStatement::STATUS_PAID)
        ->and($statement->payment_source_id)->toBe($cashSource->id);

    $this->assertDatabaseHas('expenses', [
        'source_id' => $cashSource->id,
        'occurrence_type' => Expense::OCCURRENCE_INVOICE_PAYMENT,
        'amount' => 30000,
        'status' => 'paid',
    ]);
});

test('pay statement bloqueia fatura já paga, sem valor e fonte cartão', function (): void {
    $user = User::factory()->create();
    $cashSource = $user->sources()->where('is_default', true)->firstOrFail();
    $creditCard = Source::factory()->creditCard()->create(['user_id' => $user->id]);
    $otherCard = Source::factory()->creditCard()->create(['user_id' => $user->id]);

    $paidStatement = CreditCardStatement::factory()->create([
        'source_id' => $creditCard->id,
        'reference_month' => '2026-04-01',
        'status' => CreditCardStatement::STATUS_PAID,
        'total_amount' => 30000,
        'paid_at' => now(),
    ]);

    expect(fn () => resolve(PayCreditCardStatementAction::class)->execute($paidStatement->id, $user->id, $cashSource->id))
        ->toThrow(DomainException::class, 'Esta fatura já foi paga.');

    $emptyStatement = CreditCardStatement::factory()->create([
        'source_id' => $creditCard->id,
        'reference_month' => '2026-05-01',
        'status' => CreditCardStatement::STATUS_OPEN,
        'total_amount' => 0,
    ]);

    expect(fn () => resolve(PayCreditCardStatementAction::class)->execute($emptyStatement->id, $user->id, $cashSource->id))
        ->toThrow(DomainException::class, 'Não é possível pagar uma fatura sem valor.');

    Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $creditCard->id,
        'amount' => 1000,
        'status' => 'pending',
        'origin_type' => Expense::ORIGIN_CREDIT_CARD,
        'occurrence_type' => Expense::OCCURRENCE_PURCHASE,
        'credit_card_statement_id' => $emptyStatement->id,
    ]);

    expect(fn () => resolve(PayCreditCardStatementAction::class)->execute($emptyStatement->id, $user->id, $otherCard->id))
        ->toThrow(DomainException::class, 'A fatura deve ser paga com uma fonte de caixa.');
});
