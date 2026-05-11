<?php

declare(strict_types=1);

namespace App\DTO\Auth;

readonly class WebLoginInput
{
    public function __construct(
        public string $email,
        public string $password,
        public bool $remember,
    ) {}
}
