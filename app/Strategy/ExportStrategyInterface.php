<?php

declare(strict_types=1);

namespace App\Strategy;

use Symfony\Component\HttpFoundation\StreamedResponse;

interface ExportStrategyInterface
{
    public function execute(): StreamedResponse;

    /** @return callable(): void */
    public function generate(int $userId): callable;
}
