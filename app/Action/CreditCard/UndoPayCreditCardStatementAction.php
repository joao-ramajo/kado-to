<?php

declare(strict_types=1);

namespace App\Action\CreditCard;

use App\Models\CreditCardStatement;
use App\Models\Expense;
use App\Support\CreditCard\CreditCardStatementService;
use DomainException;
use Illuminate\Support\Facades\DB;

class UndoPayCreditCardStatementAction
{
    public function __construct(
        private readonly CreditCardStatementService $creditCardStatementService,
    ) {}

    public function execute(int $statementId, int $userId): void
    {
        DB::transaction(function () use ($statementId, $userId): void {
            $statement = CreditCardStatement::query()
                ->whereKey($statementId)
                ->whereHas('source', fn ($query) => $query->where('user_id', $userId))
                ->first();

            throw_if($statement === null, DomainException::class, 'Fatura não encontrada.');

            throw_if($statement->status !== CreditCardStatement::STATUS_PAID, DomainException::class, 'Apenas faturas pagas podem ter o pagamento desfeito.');

            Expense::query()
                ->where('credit_card_statement_id', $statement->id)
                ->where('occurrence_type', Expense::OCCURRENCE_INVOICE_PAYMENT)
                ->delete();

            Expense::query()
                ->where('credit_card_statement_id', $statement->id)
                ->where('occurrence_type', Expense::OCCURRENCE_PURCHASE)
                ->update([
                    'status' => 'pending',
                    'payment_date' => null,
                ]);

            $statement->update([
                'paid_at' => null,
                'payment_source_id' => null,
            ]);

            $this->creditCardStatementService->sync($statement);
        });
    }
}
