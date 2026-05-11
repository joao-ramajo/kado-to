<?php

declare(strict_types=1);

namespace App\DTO\Expense;

readonly class MarkExpenseAsPaidOutput
{
    public function __construct(
        public string $message,
    ) {}

    public function toArray(): array
    {
        return [
            'message' => $this->message,
        ];
    }
}
