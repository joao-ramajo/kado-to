<?php

declare(strict_types=1);

namespace App\DTO\Auth;

readonly class WebLoginOutput
{
    public function __construct(
        public bool $success,
        public ?string $errorMessage = null,
    ) {}
}
