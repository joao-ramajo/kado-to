<?php

declare(strict_types=1);

namespace App\DTO\Source;

readonly class GetSourceListOutput
{
    /** @param list<array<string, mixed>> $sources */
    public function __construct(
        public array $sources,
    ) {}

    /** @return list<array<string, mixed>> */
    public function toArray(): array
    {
        return $this->sources;
    }
}
