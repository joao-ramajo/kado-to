<?php

declare(strict_types=1);

namespace App\DTO\Category;

readonly class UpdateCategoryInput
{
    public function __construct(
        public int $id,
        public int $userId,
        public string $name,
        public string $color,
    ) {}
}
