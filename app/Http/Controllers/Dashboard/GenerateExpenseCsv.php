<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Strategy\CsvExportStrategy;

class GenerateExpenseCsv
{
    public function __construct(
        protected readonly CsvExportStrategy $csvExport
    ) {}

    public function __invoke()
    {
        return $this->csvExport->execute();
    }
}
