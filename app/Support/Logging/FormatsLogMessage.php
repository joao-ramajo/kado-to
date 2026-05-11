<?php

declare(strict_types=1);

namespace App\Support\Logging;

use Illuminate\Support\Str;

trait FormatsLogMessage
{
    protected function formatLogMessage(string $message): string
    {
        $class = class_basename(static::class);

        return '['.Str::snake($class).'] '.$message;
    }
}
