<?php

declare(strict_types=1);

namespace App\Action\Category;

use App\DTO\Category\GetCategoryListInput;
use App\DTO\Category\GetCategoryListOutput;
use App\Models\Category;
use App\Support\Logging\FormatsLogMessage;
use Illuminate\Database\Eloquent\Builder;
use Psr\Log\LoggerInterface;

class GetCategoryListAction
{
    use FormatsLogMessage;

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(GetCategoryListInput $input): GetCategoryListOutput
    {
        $this->logger->info($this->formatLogMessage('started'), [
            'user_id' => $input->userId,
            'month_filter' => $input->month,
        ]);

        $startedAt = microtime(true);

        $applyMonthFilter = static function (Builder $query) use ($input): void {
            if ($input->month !== null) {
                $query->whereMonth('created_at', $input->month);
            }
        };

        /** @var list<array<string, mixed>> $categories */
        $categories = array_values(Category::query()
            ->where(function (Builder $q) use ($input): void {
                $q->where('user_id', $input->userId)
                    ->orWhereNull('user_id');
            })
            ->withCount([
                'expenses as expenses_count' => function (Builder $q) use ($input, $applyMonthFilter): void {
                    $q->where('user_id', $input->userId);
                    $applyMonthFilter($q);
                },
            ])
            ->withSum([
                'expenses as expenses_total_amount' => function (Builder $q) use ($input, $applyMonthFilter): void {
                    $q->where('user_id', $input->userId)->where('type', 'expense');
                    $applyMonthFilter($q);
                },
            ], 'amount')
            ->orderBy('name', 'asc')
            ->get()
            ->map(static fn (Category $category): array => $category->toArray())
            ->values()
            ->all());

        $this->logger->info($this->formatLogMessage('completed'), [
            'user_id' => $input->userId,
            'month_filter' => $input->month,
            'count' => count($categories),
            'query_time_ms' => (int) ((microtime(true) - $startedAt) * 1000),
        ]);

        return new GetCategoryListOutput($categories);
    }
}
