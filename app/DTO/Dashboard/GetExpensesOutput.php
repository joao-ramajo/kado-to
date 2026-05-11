<?php

declare(strict_types=1);

namespace App\DTO\Dashboard;

readonly class GetExpensesOutput
{
    /** @param list<array<string, mixed>> $items */
    public function __construct(
        public array $items,
    ) {}

    /** @return list<array<string, mixed>> */
    public function toArray(): array
    {
        return $this->items;
    }
}
