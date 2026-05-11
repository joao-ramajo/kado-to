<?php

declare(strict_types=1);

namespace App\Http\Controllers\Expense;

use App\Action\ImportCsvData;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class ImportExpenseCsvController extends Controller
{
    public function __construct(
        protected readonly ImportCsvData $importCsvDataAction
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var UploadedFile $file */
        $file = $request->file('file');

        $this->importCsvDataAction->execute($file);

        return response()
            ->json([
                'message' => 'Despesas importadas com sucesso.',
            ]);
    }
}
