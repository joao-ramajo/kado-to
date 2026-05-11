<?php

declare(strict_types=1);

namespace App\Domain;

use Illuminate\Support\Facades\Crypt;

class Uuid
{
    public function __construct(
        public readonly string $id
    ) {}

    public function value(): string|int
    {
        $value = Crypt::decrypt($this->id);

        if (is_string($value) || is_int($value)) {
            return $value;
        }

        return '';
    }
}
