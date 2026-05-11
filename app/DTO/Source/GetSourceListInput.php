<?php

declare(strict_types=1);

namespace App\DTO\Source;

readonly class GetSourceListInput
{
    public function __construct(
        public int $userId,
    ) {}
}
