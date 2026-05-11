<?php

declare(strict_types=1);

namespace App\Action\Expense;

use App\DTO\Expense\MarkExpenseAsPaidInput;
use App\DTO\Expense\MarkExpenseAsPaidOutput;
use App\Models\Expense;
use App\Support\Logging\FormatsLogMessage;
use DomainException;
use Psr\Log\LoggerInterface;

class MarkExpenseAsPaidAction
{
    use FormatsLogMessage;

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(MarkExpenseAsPaidInput $input): MarkExpenseAsPaidOutput
    {
        $this->logger->info($this->formatLogMessage('started'), [
            'user_id' => $input->userId,
            'expense_id' => $input->expenseId,
        ]);

        $expense = Expense::find($input->expenseId);

        if (! $expense || $expense->user_id !== $input->userId) {
            $this->logger->warning($this->formatLogMessage('expense not found for user'), [
                'user_id' => $input->userId,
                'expense_id' => $input->expenseId,
            ]);
            throw new DomainException('Despesa não encontrada.');
        }

        if (! in_array($expense->status, ['pending', 'overdue'], true)) {
            $this->logger->warning($this->formatLogMessage('invalid status transition'), [
                'user_id' => $input->userId,
                'expense_id' => $input->expenseId,
                'status' => $expense->status,
            ]);
            throw new DomainException('Apenas despesas pendentes ou atrasadas podem ser marcadas como pagas.');
        }

        if ($expense->origin_type === Expense::ORIGIN_CREDIT_CARD) {
            throw new DomainException('Compras no cartão devem ser quitadas pelo pagamento da fatura.');
        }

        $expense->update([
            'status' => 'paid',
            'payment_date' => now(),
        ]);

        $this->logger->info($this->formatLogMessage('completed'), [
            'user_id' => $input->userId,
            'expense_id' => $input->expenseId,
        ]);

        return new MarkExpenseAsPaidOutput('Despesa marcada como paga com sucesso.');
    }
}
