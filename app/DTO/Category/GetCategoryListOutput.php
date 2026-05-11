<?php

declare(strict_types=1);

namespace App\DTO\Category;

readonly class GetCategoryListOutput
{
    /** @param list<array<string, mixed>> $categories */
    public function __construct(
        public array $categories,
    ) {}

    /** @return list<array<string, mixed>> */
    public function toArray(): array
    {
        return $this->categories;
    }
}
