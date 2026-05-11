<?php

declare(strict_types=1);

namespace App\DTO\Auth;

readonly class WebLogoutInput
{
    public function __construct(
        public int $userId,
    ) {}
}
