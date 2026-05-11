<?php

declare(strict_types=1);

namespace App\Services;

use App\Strategy\CsvExportStrategy;
use App\Strategy\XlsxExportStrategy;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportService
{
    public function execute(Request $request): StreamedResponse
    {
        $mapper = [
            'csv' => CsvExportStrategy::class,
            'xlsx' => XlsxExportStrategy::class,
        ];

        $type = $request->get('type') ?? 'csv';

        $final = app($mapper[$type]);

        return $final->execute();
    }
}
