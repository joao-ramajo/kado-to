<?php

declare(strict_types=1);

namespace App\DTO\Xlsx;

readonly class GenerateExpensesXlsxInput
{
    public function __construct(
        public int $userId,
    ) {}
}
