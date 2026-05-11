<?php

declare(strict_types=1);

namespace App\DTO\Category;

readonly class GetCategoryListInput
{
    public function __construct(
        public int $userId,
        public ?int $month,
    ) {}
}
