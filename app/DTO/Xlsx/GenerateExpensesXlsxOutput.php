<?php

declare(strict_types=1);

namespace App\DTO\Xlsx;

use Symfony\Component\HttpFoundation\StreamedResponse;

readonly class GenerateExpensesXlsxOutput
{
    public function __construct(
        public StreamedResponse $response,
    ) {}
}
