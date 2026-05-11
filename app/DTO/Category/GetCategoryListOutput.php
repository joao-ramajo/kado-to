<?php

declare(strict_types=1);

namespace App\DTO\Category;

readonly class GetCategoryListOutput
{
    public function __construct(
        public array $categories,
    ) {}

    public function toArray(): array
    {
        return $this->categories;
    }
}
