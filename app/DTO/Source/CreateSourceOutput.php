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

    /** @return array{message: string, data: array<string, mixed>} */
    public function toArray(): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->source->toArray();

        return [
            'message' => $this->message,
            'data' => $data,
        ];
    }
}
