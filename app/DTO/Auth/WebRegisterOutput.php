<?php

declare(strict_types=1);

namespace App\DTO\Auth;

use App\Models\User;

readonly class WebRegisterOutput
{
    public function __construct(
        public User $user,
    ) {}
}
