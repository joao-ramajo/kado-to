<?php

declare(strict_types=1);

namespace App\DTO\Category;

readonly class UpdateCategoryOutput
{
    public function __construct(
        public string $message,
    ) {}

    /** @return array{message: string} */
    public function toArray(): array
    {
        return [
            'message' => $this->message,
        ];
    }
}
