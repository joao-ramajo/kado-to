<?php

declare(strict_types=1);

namespace App\Action\CreditCard;

use App\Models\CreditCardStatement;
use App\Models\Expense;
use App\Models\Source;
use App\Support\CreditCard\CreditCardStatementService;
use DomainException;
use Illuminate\Support\Facades\DB;

class PayCreditCardStatementAction
{
    public function __construct(
        private readonly CreditCardStatementService $creditCardStatementService,
    ) {}

    public function execute(int $statementId, int $userId, int $paymentSourceId): void
    {
        DB::transaction(function () use ($statementId, $userId, $paymentSourceId): void {
            $statement = CreditCardStatement::query()
                ->whereKey($statementId)
                ->whereHas('source', fn ($query) => $query->where('user_id', $userId))
                ->first();

            throw_if($statement === null, DomainException::class, 'Fatura não encontrada.');

            $statement = $this->creditCardStatementService->sync($statement);

            throw_if($statement->status === CreditCardStatement::STATUS_PAID, DomainException::class, 'Esta fatura já foi paga.');

            throw_if($statement->total_amount <= 0, DomainException::class, 'Não é possível pagar uma fatura sem valor.');

            $paymentSource = Source::query()
                ->where('user_id', $userId)
                ->whereKey($paymentSourceId)
                ->first();

            throw_if($paymentSource === null, DomainException::class, 'Fonte de pagamento não encontrada.');

            throw_if($paymentSource->isCreditCard(), DomainException::class, 'A fatura deve ser paga com uma fonte de caixa.');

            Expense::query()->create([
                'title' => sprintf(
                    'Pagamento de fatura - %s - %s',
                    $statement->source->name,
                    $statement->reference_month->format('m/Y')
                ),
                'amount' => $statement->total_amount,
                'type' => 'expense',
                'status' => 'paid',
                'user_id' => $userId,
                'category_id' => null,
                'payment_date' => now(),
                'due_date' => $statement->due_at,
                'purchase_date' => $statement->reference_month->toDateString(),
                'source_id' => $paymentSource->id,
                'credit_card_statement_id' => $statement->id,
                'origin_type' => Expense::ORIGIN_DIRECT,
                'occurrence_type' => Expense::OCCURRENCE_INVOICE_PAYMENT,
            ]);

            Expense::query()
                ->where('credit_card_statement_id', $statement->id)
                ->where('occurrence_type', Expense::OCCURRENCE_PURCHASE)
                ->update([
                    'status' => 'paid',
                    'payment_date' => now(),
                ]);

            $statement->update([
                'status' => CreditCardStatement::STATUS_PAID,
                'paid_at' => now(),
                'payment_source_id' => $paymentSource->id,
            ]);
        });
    }
}
