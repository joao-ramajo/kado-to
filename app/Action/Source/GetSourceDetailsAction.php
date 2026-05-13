<?php

declare(strict_types=1);

namespace App\Action\Source;

use App\DTO\Source\GetSourceDetailsInput;
use App\DTO\Source\GetSourceDetailsOutput;
use App\Models\CreditCardStatement;
use App\Models\Expense;
use App\Models\Source;
use App\Support\CreditCard\CreditCardStatementService;
use App\Support\Logging\FormatsLogMessage;
use Psr\Log\LoggerInterface;

class GetSourceDetailsAction
{
    use FormatsLogMessage;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly CreditCardStatementService $creditCardStatementService,
    ) {}

    public function execute(GetSourceDetailsInput $input): GetSourceDetailsOutput
    {
        $this->logger->info($this->formatLogMessage('started'), [
            'user_id' => $input->userId,
        ]);

        $startedAt = microtime(true);
        $sources = Source::query()->where('user_id', $input->userId)->get();

        /** @var list<array<string, mixed>> $items */
        $items = array_values($sources->map(function (Source $source) use ($input): array {
            if ($source->isCreditCard()) {
                $currentStatement = CreditCardStatement::query()->where('source_id', $source->id)
                    ->where('status', '!=', CreditCardStatement::STATUS_PAID)
                    ->oldest('due_at')
                    ->first();

                $lastPaidStatement = CreditCardStatement::query()->where('source_id', $source->id)
                    ->where('status', CreditCardStatement::STATUS_PAID)
                    ->latest('paid_at')
                    ->first();

                if ($currentStatement !== null) {
                    $currentStatement = $this->creditCardStatementService->sync($currentStatement);
                }

                $usedLimit = Expense::query()->where('user_id', $input->userId)
                    ->where('source_id', $source->id)
                    ->where('occurrence_type', Expense::OCCURRENCE_PURCHASE)
                    ->where('status', '!=', 'paid')
                    ->sum('amount');

                $expensesCount = Expense::query()->where('user_id', $input->userId)
                    ->where('source_id', $source->id)
                    ->where('occurrence_type', Expense::OCCURRENCE_PURCHASE)
                    ->count();

                return [
                    'id' => $source->id,
                    'name' => $source->name,
                    'type' => $source->type,
                    'color' => $source->color,
                    'is_default' => $source->is_default,
                    'allow_negative' => $source->allow_negative,
                    'statement_closing_day' => $source->statement_closing_day,
                    'statement_due_day' => $source->statement_due_day,
                    'expenses_count' => $expensesCount,
                    'credit_limit' => $source->credit_limit,
                    'used_limit' => $usedLimit,
                    'available_limit' => max(0, (int) $source->credit_limit - (int) $usedLimit),
                    'current_statement' => $currentStatement instanceof CreditCardStatement ? [
                        'id' => $currentStatement->id,
                        'reference_month' => $currentStatement->reference_month->toDateString(),
                        'closing_at' => $currentStatement->closing_at->toDateString(),
                        'due_at' => $currentStatement->due_at->toDateString(),
                        'status' => $currentStatement->status,
                        'total_amount' => $currentStatement->total_amount,
                    ] : null,
                    'last_paid_statement' => $lastPaidStatement instanceof CreditCardStatement ? [
                        'id' => $lastPaidStatement->id,
                        'reference_month' => $lastPaidStatement->reference_month->toDateString(),
                        'closing_at' => $lastPaidStatement->closing_at->toDateString(),
                        'due_at' => $lastPaidStatement->due_at->toDateString(),
                        'status' => $lastPaidStatement->status,
                        'total_amount' => $lastPaidStatement->total_amount,
                    ] : null,
                ];
            }

            $totalIncome = Expense::query()->where('user_id', $input->userId)
                ->where('source_id', $source->id)
                ->where('type', 'income')
                ->sum('amount');

            $totalExpense = Expense::query()->where('user_id', $input->userId)
                ->where('source_id', $source->id)
                ->where('type', 'expense')
                ->sum('amount');

            $expensesCount = Expense::query()->where('user_id', $input->userId)
                ->where('source_id', $source->id)
                ->count();

            return [
                'id' => $source->id,
                'name' => $source->name,
                'type' => $source->type,
                'color' => $source->color,
                'is_default' => $source->is_default,
                'allow_negative' => $source->allow_negative,
                'statement_closing_day' => $source->statement_closing_day,
                'statement_due_day' => $source->statement_due_day,
                'expenses_count' => $expensesCount,
                'total_income' => $totalIncome,
                'total_expense' => $totalExpense,
                'balance' => $totalIncome - $totalExpense,
                'credit_limit' => null,
                'used_limit' => null,
                'available_limit' => null,
                'current_statement' => null,
                'last_paid_statement' => null,
            ];
        })->all());

        $this->logger->info($this->formatLogMessage('completed'), [
            'user_id' => $input->userId,
            'count' => count($items),
            'query_time_ms' => (int) ((microtime(true) - $startedAt) * 1000),
        ]);

        return new GetSourceDetailsOutput($items);
    }
}
