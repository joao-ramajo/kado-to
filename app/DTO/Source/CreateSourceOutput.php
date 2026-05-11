<?php

declare(strict_types=1);

namespace App\DTO\Source;

use App\Models\Source;

readonly class CreateSourceOutput
{
    public function __construct(
        public string $message,
        public Source $source,
    ) {}

    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'data' => $this->source->toArray(),
        ];
    }
}
