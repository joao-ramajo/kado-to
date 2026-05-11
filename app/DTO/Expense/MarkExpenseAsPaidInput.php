<?php

declare(strict_types=1);

namespace App\DTO\Expense;

readonly class MarkExpenseAsPaidInput
{
    public function __construct(
        public int $expenseId,
        public int $userId,
    ) {}
}
