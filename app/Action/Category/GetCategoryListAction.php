<?php

declare(strict_types=1);

namespace App\Action\Category;

use App\DTO\Category\GetCategoryListInput;
use App\DTO\Category\GetCategoryListOutput;
use App\Models\Category;
use App\Support\Logging\FormatsLogMessage;
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

        $applyMonthFilter = function ($query) use ($input) {
            if ($input->month !== null) {
                $query->whereMonth('created_at', $input->month);
            }
        };

        $categories = Category::query()
            ->where(function ($q) use ($input) {
                $q->where('user_id', $input->userId)
                    ->orWhereNull('user_id');
            })
            ->withCount([
                'expenses as expenses_count' => function ($q) use ($input, $applyMonthFilter) {
                    $q->where('user_id', $input->userId);
                    $applyMonthFilter($q);
                },
            ])
            ->withSum([
                'expenses as expenses_total_amount' => function ($q) use ($input, $applyMonthFilter) {
                    $q->where('user_id', $input->userId)->where('type', 'expense');
                    $applyMonthFilter($q);
                },
            ], 'amount')
            ->orderBy('name', 'asc')
            ->get()
            ->toArray();

        $this->logger->info($this->formatLogMessage('completed'), [
            'user_id' => $input->userId,
            'month_filter' => $input->month,
            'count' => count($categories),
            'query_time_ms' => (int) ((microtime(true) - $startedAt) * 1000),
        ]);

        return new GetCategoryListOutput($categories);
    }
}
