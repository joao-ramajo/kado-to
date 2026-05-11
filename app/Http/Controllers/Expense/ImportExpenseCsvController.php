<?php

declare(strict_types=1);

namespace App\Http\Controllers\Expense;

use App\Action\ImportCsvData;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ImportExpenseCsvController extends Controller
{
    public function __construct(
        protected readonly ImportCsvData $importCsvDataAction
    ) {}

    public function __invoke(Request $request)
    {
        $file = $request->file('file');

        $this->importCsvDataAction->execute($file);

        return response()
            ->json([
                'message' => 'Despesas importadas com sucesso.',
            ]);
    }
}
