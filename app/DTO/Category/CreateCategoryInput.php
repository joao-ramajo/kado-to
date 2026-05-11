<?php

declare(strict_types=1);

namespace App\DTO\Category;

readonly class CreateCategoryInput
{
    public function __construct(
        public int $userId,
        public string $name,
        public string $color,
    ) {}
}
