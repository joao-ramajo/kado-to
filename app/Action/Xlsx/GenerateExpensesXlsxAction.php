<?php

declare(strict_types=1);

namespace App\Action\Xlsx;

use App\DTO\Xlsx\GenerateExpensesXlsxInput;
use App\DTO\Xlsx\GenerateExpensesXlsxOutput;
use App\Support\Logging\FormatsLogMessage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GenerateExpensesXlsxAction
{
    use FormatsLogMessage;

    public function __construct(
        private readonly ExpensesListSheet $expensesListSheet,
        private readonly SourcesSummarySheet $sourcesSummarySheet,
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(GenerateExpensesXlsxInput $input): GenerateExpensesXlsxOutput
    {
        $this->logger->info($this->formatLogMessage('started'), [
            'user_id' => $input->userId,
        ]);

        $spreadsheet = new Spreadsheet;
        $this->expensesListSheet->addTo($spreadsheet);
        $this->sourcesSummarySheet->addTo($spreadsheet);

        $response = $this->generateResponse($spreadsheet);

        $this->logger->info($this->formatLogMessage('completed'), [
            'user_id' => $input->userId,
            'file_name' => 'despesas.xlsx',
        ]);

        return new GenerateExpensesXlsxOutput($response);
    }

    private function generateResponse(Spreadsheet $spreadsheet): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });

        $response->headers->set(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
        $response->headers->set(
            'Content-Disposition',
            'attachment;filename="despesas.xlsx"'
        );
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }
}
