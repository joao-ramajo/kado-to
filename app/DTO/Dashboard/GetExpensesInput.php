<?php

declare(strict_types=1);

namespace App\DTO\Dashboard;

readonly class GetExpensesInput
{
    public function __construct(
        public int $userId,
        public ?string $status,
        public ?string $query,
        public ?int $categoryId,
        public ?int $sourceId,
        public ?int $month,
    ) {}
}
