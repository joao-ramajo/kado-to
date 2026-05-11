<?php

declare(strict_types=1);

namespace App\Action\Expense;

use App\Models\Expense;
use DomainException;

class DeleteExpenseAction
{
    public function execute(int $expenseId, int $userId): void
    {
        $expense = Expense::find($expenseId);

        if (! $expense) {
            throw new DomainException('Despesa não encontrada.');
        }

        if ($expense->user_id !== $userId) {
            throw new DomainException('Você não tem permissão para deletar esta despesa.');
        }

        if ($expense->origin_type === Expense::ORIGIN_CREDIT_CARD || $expense->occurrence_type === Expense::OCCURRENCE_INVOICE_PAYMENT) {
            throw new DomainException('Registros de cartão devem ser gerenciados pelo fluxo da fatura.');
        }

        $expense->delete();
    }
}
