<?php

declare(strict_types=1);

namespace App\Action\Dashboard;

use App\DTO\Dashboard\GetSummaryInput;
use App\DTO\Dashboard\GetSummaryOutput;
use App\Models\CreditCardStatement;
use App\Models\Expense;
use App\Models\Source;
use App\Support\Logging\FormatsLogMessage;
use Psr\Log\LoggerInterface;

class GetSummaryAction
{
    use FormatsLogMessage;

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(GetSummaryInput $input): GetSummaryOutput
    {
        $this->logger->info($this->formatLogMessage('started'), [
            'user_id' => $input->userId,
            'default_source_id' => $input->defaultSourceId,
        ]);

        $startedAt = microtime(true);

        $cashExpenseQuery = Expense::query()
            ->join('sources', 'expenses.source_id', '=', 'sources.id')
            ->where('expenses.user_id', $input->userId)
            ->where('sources.type', Source::TYPE_CASH_LIKE)
            ->where('expenses.source_id', $input->defaultSourceId);

        $totalReceive = (clone $cashExpenseQuery)
            ->where('expenses.type', 'income')
            ->where('expenses.status', 'paid')
            ->sum('expenses.amount');

        $totalExpense = (clone $cashExpenseQuery)
            ->where('expenses.type', 'expense')
            ->where('expenses.status', 'paid')
            ->sum('expenses.amount');

        $totalIncomePending = (clone $cashExpenseQuery)
            ->where('expenses.type', 'income')
            ->where('expenses.status', 'pending')
            ->sum('expenses.amount');

        $totalExpensePending = (clone $cashExpenseQuery)
            ->where('expenses.type', 'expense')
            ->where('expenses.status', 'pending')
            ->sum('expenses.amount');

        $expectedTotal = ($totalReceive + $totalIncomePending) - ($totalExpense + $totalExpensePending);
        $creditCardOpenTotal = CreditCardStatement::query()
            ->join('sources', 'credit_card_statements.source_id', '=', 'sources.id')
            ->where('sources.user_id', $input->userId)
            ->where('credit_card_statements.status', '!=', CreditCardStatement::STATUS_PAID)
            ->sum('credit_card_statements.total_amount');
        $creditCardLimitUsed = Expense::query()
            ->join('sources', 'expenses.source_id', '=', 'sources.id')
            ->where('expenses.user_id', $input->userId)
            ->where('sources.type', Source::TYPE_CREDIT_CARD)
            ->where('expenses.occurrence_type', Expense::OCCURRENCE_PURCHASE)
            ->where('expenses.status', '!=', 'paid')
            ->sum('expenses.amount');

        $this->logger->info($this->formatLogMessage('completed'), [
            'user_id' => $input->userId,
            'total_receive' => $totalReceive,
            'total_expense' => $totalExpense,
            'expected_total' => $expectedTotal,
            'credit_card_open_total' => $creditCardOpenTotal,
            'credit_card_limit_used' => $creditCardLimitUsed,
            'query_time_ms' => (int) ((microtime(true) - $startedAt) * 1000),
        ]);

        return new GetSummaryOutput(
            (int) $totalReceive,
            (int) $totalExpense,
            (int) $expectedTotal,
            (int) $creditCardOpenTotal,
            (int) $creditCardLimitUsed,
        );
    }
}
