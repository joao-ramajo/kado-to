<?php

declare(strict_types=1);

namespace App\DTO\Source;

readonly class GetSourceListOutput
{
    public function __construct(
        public array $sources,
    ) {}

    public function toArray(): array
    {
        return $this->sources;
    }
}
