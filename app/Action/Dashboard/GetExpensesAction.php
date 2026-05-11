<?php

declare(strict_types=1);

namespace App\Action\Dashboard;

use App\DTO\Dashboard\GetExpensesInput;
use App\DTO\Dashboard\GetExpensesOutput;
use App\Models\Expense;
use App\Support\Logging\FormatsLogMessage;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use stdClass;

class GetExpensesAction
{
    use FormatsLogMessage;

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(GetExpensesInput $input): GetExpensesOutput
    {
        $this->logger->info($this->formatLogMessage('started'), [
            'user_id' => $input->userId,
            'status_filter' => $input->status,
            'query_filter' => $input->query,
            'category_id_filter' => $input->categoryId,
            'month_filter' => $input->month,
        ]);

        $startedAt = microtime(true);

        $query = DB::table('expenses')
            ->leftJoin('categories', 'expenses.category_id', '=', 'categories.id')
            ->join('sources', 'expenses.source_id', '=', 'sources.id')
            ->leftJoin('credit_card_statements', 'expenses.credit_card_statement_id', '=', 'credit_card_statements.id')
            ->where('expenses.user_id', $input->userId);

        if ($input->status !== null && $input->status !== 'all') {
            $query->where('expenses.status', $input->status);
        }

        if ($input->categoryId !== null) {
            $query->where('expenses.category_id', $input->categoryId);
        }

        if ($input->month !== null) {
            if ($input->categoryId !== null) {
                $query->where(function (Builder $monthQuery) use ($input): void {
                    $monthQuery
                        ->where(function (Builder $paidQuery) use ($input): void {
                            $paidQuery
                                ->where('expenses.occurrence_type', Expense::OCCURRENCE_PURCHASE)
                                ->whereMonth('expenses.due_date', $input->month);
                        })
                        ->orWhere(function (Builder $paidQuery) use ($input): void {
                            $paidQuery
                                ->where('expenses.occurrence_type', '!=', Expense::OCCURRENCE_PURCHASE)
                                ->where('expenses.status', 'paid')
                                ->whereNotNull('expenses.payment_date')
                                ->whereMonth('expenses.payment_date', $input->month);
                        })
                        ->orWhere(function (Builder $unpaidQuery) use ($input): void {
                            $unpaidQuery
                                ->where('expenses.occurrence_type', '!=', Expense::OCCURRENCE_PURCHASE)
                                ->where('expenses.status', '!=', 'paid')
                                ->whereMonth('expenses.created_at', $input->month);
                        });
                });
            } else {
                $query->where(function (Builder $monthQuery) use ($input): void {
                    $monthQuery
                        ->where(function (Builder $purchaseQuery) use ($input): void {
                            $purchaseQuery
                                ->where('expenses.occurrence_type', Expense::OCCURRENCE_PURCHASE)
                                ->whereMonth('expenses.due_date', $input->month);
                        })
                        ->orWhere(function (Builder $defaultQuery) use ($input): void {
                            $defaultQuery
                                ->where('expenses.occurrence_type', '!=', Expense::OCCURRENCE_PURCHASE)
                                ->whereMonth('expenses.created_at', $input->month);
                        });
                });
            }
        }

        $expenses = $query
            ->latest('expenses.created_at')
            ->select(
                'expenses.id',
                'expenses.title',
                'categories.name as category',
                'categories.id as category_id',
                'expenses.amount',
                'expenses.payment_date',
                'expenses.due_date',
                'expenses.type',
                'expenses.status',
                'expenses.source_id',
                'sources.type as source_type',
                'sources.name as source_name',
                'expenses.origin_type',
                'expenses.occurrence_type',
                'expenses.installment_number',
                'expenses.installment_total',
                'expenses.purchase_date',
                'expenses.credit_card_statement_id',
                'credit_card_statements.reference_month as statement_reference_month',
            )
            ->get();

        if ($input->query !== null && trim($input->query) !== '') {
            $normalizedQuery = $this->normalizeSearchTerm($input->query);
            $expenses = $expenses->filter(function (stdClass $expense) use ($normalizedQuery): bool {
                if (! isset($expense->title) || ! is_string($expense->title)) {
                    return false;
                }

                $normalizedTitle = $this->normalizeSearchTerm($expense->title);

                return str_contains($normalizedTitle, $normalizedQuery);
            })->values();
        }

        /** @var list<array<string, mixed>> $expenses */
        $expenses = array_values($expenses->map(
            static fn (stdClass $expense): array => get_object_vars($expense)
        )->all());

        $this->logger->info($this->formatLogMessage('completed'), [
            'user_id' => $input->userId,
            'category_id_filter' => $input->categoryId,
            'month_filter' => $input->month,
            'count' => count($expenses),
            'query_time_ms' => (int) ((microtime(true) - $startedAt) * 1000),
        ]);

        return new GetExpensesOutput($expenses);
    }

    private function normalizeSearchTerm(string $value): string
    {
        $normalized = Str::ascii(mb_strtolower($value));
        $normalized = preg_replace('/[^\pL\pN\s]/u', '', $normalized) ?? '';
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? '';

        return trim($normalized);
    }
}
