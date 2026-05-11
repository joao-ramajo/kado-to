<?php

declare(strict_types=1);

namespace App\Action\Xlsx;

use App\Domain\Interfaces\XlsxSheet;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SourcesSummarySheet implements XlsxSheet
{
    private const HEADER_BG = 'F3F4F6';

    private const HEADER_FONT = '111827';

    private const TABLE_BORDER_COLOR = 'D1D5DB';

    private const STRIPE_BG = 'F9FAFB';

    public function addTo(Spreadsheet $spreadsheet): void
    {
        $sheet = new Worksheet($spreadsheet, 'Resumo por Fonte');
        $spreadsheet->addSheet($sheet);

        $this->setupHeaders($sheet);

        $rawData = $this->getValues();
        $data = $this->normalizeValues($rawData);

        $sheet->fromArray($data, null, 'A2');

        $lastRow = count($data) + 1;
        $totalRow = $lastRow;

        $this->setupColumnWidths($sheet);
        $this->applyHeaderStyles($sheet);
        $this->applyRowStyles($sheet, $lastRow, $totalRow);
        $this->applyCellFormats($sheet, $lastRow);
        $this->freezeHeader($sheet);
        $sheet->setAutoFilter('A1:D' . $lastRow);
    }

    private function getUserId(): int
    {
        $userId = Auth::id();

        if (is_int($userId)) {
            return $userId;
        }

        return 0;
    }

    private function setupHeaders(Worksheet $sheet): void
    {
        $sheet->fromArray([
            ['Fonte', 'Total Recebido', 'Total Gasto', 'Saldo'],
        ], null, 'A1');
    }

    private function setupColumnWidths(Worksheet $sheet): void
    {
        $sheet->getColumnDimension('A')->setWidth(30);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(18);
        $sheet->getColumnDimension('D')->setWidth(18);
    }

    /**
     * @return array<int, object{
     *     source: string|null,
     *     total_income: int|string,
     *     total_expense: int|string
     * }>
     */
    private function getValues(): array
    {
        $values = DB::table('expenses')
            ->leftJoin('sources', 'sources.id', '=', 'expenses.source_id')
            ->where('expenses.user_id', $this->getUserId())
            ->selectRaw('
                sources.name as source,
                SUM(CASE WHEN expenses.type = "income" THEN expenses.amount ELSE 0 END) as total_income,
                SUM(CASE WHEN expenses.type = "expense" THEN expenses.amount ELSE 0 END) as total_expense
            ')
            ->groupBy('sources.name')
            ->get()
            ->toArray();

        /** @var array<int, object{
         *     source: string|null,
         *     total_income: int|string,
         *     total_expense: int|string
         * }> $values
         */
        return $values;
    }

    /**
     * @param array<int, object{
     *     source: string|null,
     *     total_income: int|string,
     *     total_expense: int|string
     * }> $values
     * @return list<list<string|float>>
     */
    private function normalizeValues(array $values): array
    {
        $totalIncome = 0;
        $totalExpense = 0;

        $rows = array_values(array_map(function (object $row) use (&$totalIncome, &$totalExpense): array {
            $income = (int) $row->total_income;
            $expense = (int) $row->total_expense;

            $totalIncome += $income;
            $totalExpense += $expense;

            return [
                $row->source ?? '—',
                $this->normalizeMoney($income),
                $this->normalizeMoney($expense),
                $this->normalizeMoney($income - $expense),
            ];
        }, $values));

        // Linha de total
        $rows[] = [
            'TOTAL',
            $this->normalizeMoney($totalIncome),
            $this->normalizeMoney($totalExpense),
            $this->normalizeMoney($totalIncome - $totalExpense),
        ];

        return $rows;
    }

    private function applyHeaderStyles(Worksheet $sheet): void
    {
        $sheet->getStyle('A1:D1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 11,
                'color' => ['rgb' => self::HEADER_FONT],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => self::HEADER_BG],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => self::TABLE_BORDER_COLOR],
                ],
            ],
        ]);

        $sheet->getRowDimension(1)->setRowHeight(32);
    }

    private function applyRowStyles(Worksheet $sheet, int $lastRow, int $totalRow): void
    {
        if ($lastRow < 2) {
            return;
        }

        $sheet->getStyle('A2:D' . $lastRow)->applyFromArray([
            'font' => [
                'size' => 10,
                'color' => ['rgb' => self::HEADER_FONT],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => self::TABLE_BORDER_COLOR],
                ],
            ],
        ]);

        for ($i = 2; $i <= $lastRow; $i++) {
            $sheet->getRowDimension($i)->setRowHeight(22);

            $sheet->getStyle(sprintf('A%d:D%d', $i, $i))
                ->getAlignment()
                ->setVertical(Alignment::VERTICAL_CENTER);

            $sheet->getStyle(sprintf('B%d:D%d', $i, $i))
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            if ($i % 2 === 0) {
                $sheet->getStyle(sprintf('A%d:D%d', $i, $i))->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => self::STRIPE_BG],
                    ],
                ]);
            }
        }

        $sheet->getStyle(sprintf('A%d:D%d', $totalRow, $totalRow))->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => self::HEADER_FONT],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E5E7EB'],
            ],
        ]);
    }

    private function freezeHeader(Worksheet $sheet): void
    {
        $sheet->freezePane('A2');
    }

    private function applyCellFormats(Worksheet $sheet, int $lastRow): void
    {
        if ($lastRow < 2) {
            return;
        }

        $sheet->getStyle('B2:D' . $lastRow)
            ->getNumberFormat()
            ->setFormatCode('[$R$-416] #,##0.00');
    }

    private function normalizeMoney(int|string $amount): float
    {
        return ((int) $amount) / 100;
    }
}
