<?php

declare(strict_types=1);

namespace App\DTO\Source;

readonly class GetSourceDetailsOutput
{
    public function __construct(
        public array $items,
    ) {}

    public function toArray(): array
    {
        return $this->items;
    }
}
