<?php

declare(strict_types=1);

namespace App\DTO\Auth;

readonly class WebLogoutOutput
{
    public function __construct(
        public bool $success,
    ) {}
}
