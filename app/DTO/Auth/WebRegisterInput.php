<?php

declare(strict_types=1);

namespace App\DTO\Auth;

readonly class WebRegisterInput
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
    ) {}
}
