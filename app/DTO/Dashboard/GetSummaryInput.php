<?php

declare(strict_types=1);

namespace App\DTO\Dashboard;

readonly class GetSummaryInput
{
    public function __construct(
        public int $userId,
        public ?int $defaultSourceId,
    ) {}
}
