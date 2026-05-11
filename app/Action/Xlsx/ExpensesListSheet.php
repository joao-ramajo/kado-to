<?php

declare(strict_types=1);

namespace App\Action\Xlsx;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use App\Domain\Interfaces\XlsxSheet;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class ExpensesListSheet implements XlsxSheet
{
    private const HEADER_RANGE = 'A1:G1';

    private const HEADER_ROW_HEIGHT = 32;

    private const DATA_ROW_HEIGHT = 22;

    private const TABLE_BORDER_COLOR = 'D1D5DB';

    private const HEADER_BG = 'F3F4F6';

    private const HEADER_FONT = '111827';

    private const STRIPE_BG = 'F9FAFB';

    public function addTo(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Despesas');

        $rawData = $this->getValues();
        $data = $this->normalizeValues($rawData);

        $this->setupHeaders($sheet);
        $this->setupColumnWidths($sheet);
        $this->insertData($sheet, $data);

        $lastRow = count($data) + 1;

        $this->setupRowHeights($sheet, $lastRow);
        $this->applyHeaderStyles($sheet);
        $this->applyTableStyles($sheet, $lastRow);
        $this->applyColumnAlignments($sheet, $lastRow);
        $this->applyCellFormats($sheet, $lastRow);
        $this->applyConditionalFormatting($sheet, $rawData);
        $this->freezeHeader($sheet);
        $sheet->setAutoFilter('A1:G' . $lastRow);
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
        $headers = $this->getHeaders();
        $sheet->fromArray([$headers], null, 'A1');
    }

    private function setupColumnWidths(Worksheet $sheet): void
    {
        $widths = [
            'A' => 35, // Descrição
            'B' => 18, // Valor
            'C' => 16, // Status
            'D' => 24, // Categoria
            'E' => 16, // Tipo
            'F' => 24, // Fonte
            'G' => 20, // Data
        ];

        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
    }

    /** @param list<list<string|float|null>> $data */
    private function insertData(Worksheet $sheet, array $data): void
    {
        $sheet->fromArray($data, null, 'A2');
    }

    private function setupRowHeights(Worksheet $sheet, int $lastRow): void
    {
        // Cabeçalho
        $sheet->getRowDimension(1)->setRowHeight(self::HEADER_ROW_HEIGHT);

        // Linhas de dados
        for ($i = 2; $i <= $lastRow; $i++) {
            $sheet->getRowDimension($i)->setRowHeight(self::DATA_ROW_HEIGHT);
        }
    }

    private function applyHeaderStyles(Worksheet $sheet): void
    {
        $sheet->getStyle(self::HEADER_RANGE)->applyFromArray([
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
    }

    private function applyTableStyles(Worksheet $sheet, int $lastRow): void
    {
        if ($lastRow < 2) {
            return;
        }

        $sheet->getStyle('A2:G' . $lastRow)->applyFromArray([
            'font' => [
                'size' => 10,
                'color' => ['rgb' => self::HEADER_FONT],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => self::TABLE_BORDER_COLOR],
                ],
            ],
        ]);

        for ($row = 2; $row <= $lastRow; $row++) {
            if ($row % 2 === 0) {
                $sheet->getStyle(sprintf('A%d:G%d', $row, $row))->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => self::STRIPE_BG],
                    ],
                ]);
            }
        }
    }

    private function applyColumnAlignments(Worksheet $sheet, int $lastRow): void
    {
        $this->applyDescriptionAlignment($sheet, $lastRow);
        $this->applyValueAlignment($sheet, $lastRow);
        $this->applyStatusAlignment($sheet, $lastRow);
        $this->applyCategoryAlignment($sheet, $lastRow);
        $this->applyTypeAlignment($sheet, $lastRow);
        $this->applySourceAlignment($sheet, $lastRow);
        $this->applyDateAlignment($sheet, $lastRow);
    }

    private function applyDescriptionAlignment(Worksheet $sheet, int $lastRow): void
    {
        $sheet->getStyle('A2:A' . $lastRow)
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT);
    }

    private function applyValueAlignment(Worksheet $sheet, int $lastRow): void
    {
        $sheet->getStyle('B2:B' . $lastRow)->applyFromArray([
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_RIGHT,
            ],
        ]);
    }

    private function applyStatusAlignment(Worksheet $sheet, int $lastRow): void
    {
        $sheet->getStyle('C2:C' . $lastRow)
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    private function applyCategoryAlignment(Worksheet $sheet, int $lastRow): void
    {
        $sheet->getStyle('D2:D' . $lastRow)
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT);
    }

    private function applyTypeAlignment(Worksheet $sheet, int $lastRow): void
    {
        $sheet->getStyle('E2:E' . $lastRow)
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    private function applySourceAlignment(Worksheet $sheet, int $lastRow): void
    {
        $sheet->getStyle('F2:F' . $lastRow)
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT);
    }

    private function applyDateAlignment(Worksheet $sheet, int $lastRow): void
    {
        $sheet->getStyle('G2:G' . $lastRow)
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    private function applyCellFormats(Worksheet $sheet, int $lastRow): void
    {
        if ($lastRow < 2) {
            return;
        }

        $sheet->getStyle('B2:B' . $lastRow)
            ->getNumberFormat()
            ->setFormatCode('[$R$-416] #,##0.00');

        $sheet->getStyle('G2:G' . $lastRow)
            ->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_DATE_DDMMYYYY);
    }

    /** @param array<int, object{status: string, type: string}> $rawData */
    private function applyConditionalFormatting(Worksheet $sheet, array $rawData): void
    {
        foreach ($rawData as $index => $row) {
            $rowNum = $index + 2;

            $this->formatStatus($sheet, $rowNum, $row->status);
            $this->formatType($sheet, $rowNum, $row->type);
        }
    }

    private function formatStatus(Worksheet $sheet, int $rowNum, string $status): void
    {
        if ($status === 'paid') {
            $sheet->getStyle('C' . $rowNum)->applyFromArray([
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => '047857'],
                ],
            ]);
        } elseif ($status === 'pending') {
            $sheet->getStyle('C' . $rowNum)->applyFromArray([
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'B45309'],
                ],
            ]);
        } elseif ($status === 'overdue') {
            $sheet->getStyle('C' . $rowNum)->applyFromArray([
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'B91C1C'],
                ],
            ]);
        }
    }

    private function formatType(Worksheet $sheet, int $rowNum, string $type): void
    {
        if ($type === 'income') {
            $this->formatAsIncome($sheet, $rowNum);
        } elseif ($type === 'expense') {
            $this->formatAsExpense($sheet, $rowNum);
        }
    }

    private function formatAsIncome(Worksheet $sheet, int $rowNum): void
    {
        $sheet->getStyle('E' . $rowNum)->applyFromArray([
            'font' => [
                'color' => ['rgb' => '1D4ED8'],
            ],
        ]);

        $sheet->getStyle('B' . $rowNum)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => '1D4ED8'],
            ],
        ]);
    }

    private function formatAsExpense(Worksheet $sheet, int $rowNum): void
    {
        $sheet->getStyle('E' . $rowNum)->applyFromArray([
            'font' => [
                'color' => ['rgb' => '334155'],
            ],
        ]);
    }

    private function freezeHeader(Worksheet $sheet): void
    {
        $sheet->freezePane('A2');
    }

    /** @return list<string> */
    private function getHeaders(): array
    {
        return [
            'Descrição',
            'Valor',
            'Status',
            'Categoria',
            'Tipo',
            'Fonte',
            'Data de Pagamento',
        ];
    }

    /**
     * @return array<int, object{
     *     title: string,
     *     amount: int|string,
     *     status: string,
     *     category: string|null,
     *     type: string,
     *     source: string|null,
     *     payment_date: string|null
     * }>
     */
    private function getValues(): array
    {
        $values = DB::table('expenses')
            ->where('expenses.user_id', $this->getUserId())
            ->leftJoin('categories', 'expenses.category_id', '=', 'categories.id')
            ->leftJoin('sources', 'expenses.source_id', '=', 'sources.id')
            ->latest('expenses.created_at')
            ->select(
                'expenses.title',
                'expenses.amount',
                'expenses.status',
                'categories.name as category',
                'expenses.type',
                'sources.name as source',
                'expenses.payment_date'
            )
            ->get()
            ->toArray();

        /** @var array<int, object{
         *     title: string,
         *     amount: int|string,
         *     status: string,
         *     category: string|null,
         *     type: string,
         *     source: string|null,
         *     payment_date: string|null
         * }> $values
         */
        return $values;
    }

    /**
     * @param array<int, object{
     *     title: string,
     *     amount: int|string,
     *     status: string,
     *     category: string|null,
     *     type: string,
     *     source: string|null,
     *     payment_date: string|null
     * }> $values
     * @return list<list<string|float|null>>
     */
    private function normalizeValues(array $values): array
    {
        return array_values(array_map(fn (object $row): array => [
            $row->title,
            $this->normalizeMoney((int) $row->amount),
            $this->translateStatus($row->status),
            $row->category ?? '-',
            $this->translateType($row->type),
            $row->source ?? '-',
            $this->toExcelDate($row->payment_date),
        ], $values));
    }

    private function normalizeMoney(int|string $amount): float
    {
        $cents = (int) $amount;

        return $cents / 100;
    }

    private function toExcelDate(?string $date): ?float
    {
        if (! $date) {
            return null;
        }

        $excelDate = ExcelDate::PHPToExcel(\Illuminate\Support\Facades\Date::parse($date));

        if (is_float($excelDate)) {
            return $excelDate;
        }

        return null;
    }

    private function translateStatus(string $status): string
    {
        return match ($status) {
            'paid' => 'Pago',
            'pending' => 'Pendente',
            default => ucfirst($status),
        };
    }

    private function translateType(string $type): string
    {
        return match ($type) {
            'income' => 'Receita',
            'expense' => 'Despesa',
            default => ucfirst($type),
        };
    }
}
