<?php

declare(strict_types=1);

namespace App\Strategy;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class XlsxExportStrategy
{
    public function execute(): StreamedResponse
    {
        $user = Auth::user();

        $name = Str::slug($user->name);

        $fileName = "{$name}-fillament-wallet-".Str::uuid().'.xlsx';

        $callback = function () use ($user) {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();

            $headerStyle = [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                    'size' => 12,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ];

            $headers = [
                'TÍTULO',
                'VALOR',
                'STATUS',
                'DATA PAGAMENTO',
                'DATA VENCIMENTO',
                'CRIADO EM',
                'CATEGORIA',
                'CONTA',
            ];

            $sheet->fromArray([$headers], null, 'A1');
            $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);

            foreach (range('A', 'H') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $row = 2;
            DB::table('expenses')
                ->leftJoin('categories', 'categories.id', '=', 'expenses.category_id')
                ->leftJoin('bank_accounts', 'bank_accounts.id', '=', 'expenses.bank_account_id')
                ->where('expenses.user_id', $user->id)
                ->select(
                    'expenses.title',
                    'expenses.amount',
                    'expenses.status',
                    'expenses.payment_date',
                    'expenses.due_date',
                    'expenses.created_at',
                    'categories.name as category_name',
                    'bank_accounts.name as bank_account_name'
                )
                ->orderBy('expenses.created_at', 'desc')
                ->chunk(1000, function ($expenses) use ($sheet, &$row) {
                    foreach ($expenses as $expense) {
                        $sheet->setCellValue('A'.$row, $expense->title ?? '-');
                        $sheet->setCellValue('B'.$row, $expense->amount);
                        $sheet->setCellValue('C'.$row, $expense->status ?? '-');
                        $sheet->setCellValue('D'.$row, $expense->payment_date ?? '-');
                        $sheet->setCellValue('E'.$row, $expense->due_date ?? '-');
                        $sheet->setCellValue('F'.$row, $expense->created_at ?? '-');
                        $sheet->setCellValue('G'.$row, $expense->category_name ?? '-');
                        $sheet->setCellValue('H'.$row, $expense->bank_account_name ?? '-');
                        $row++;
                    }
                });

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        };

        return response()->streamDownload($callback, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
