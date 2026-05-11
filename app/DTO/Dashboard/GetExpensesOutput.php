<?php

declare(strict_types=1);

namespace App\DTO\Dashboard;

readonly class GetExpensesOutput
{
    public function __construct(
        public array $items,
    ) {}

    public function toArray(): array
    {
        return $this->items;
    }
}
