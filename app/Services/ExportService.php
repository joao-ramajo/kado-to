<?php

declare(strict_types=1);

namespace App\Services;

use App\Strategy\CsvExportStrategy;
use App\Strategy\ExportStrategyInterface;
use App\Strategy\XlsxExportStrategy;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportService
{
    public function execute(Request $request): StreamedResponse
    {
        $type = $request->string('type')->toString();
        $strategy = match ($type) {
            'xlsx' => app(XlsxExportStrategy::class),
            default => app(CsvExportStrategy::class),
        };

        return $strategy->execute();
    }
}
