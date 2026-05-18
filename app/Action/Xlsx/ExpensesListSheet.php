<?php

declare(strict_types=1);

namespace App\Action\Xlsx;

use App\Domain\Interfaces\XlsxSheet;
use App\Models\Expense;
use App\Models\Source;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExpensesListSheet implements XlsxSheet
{
    private const HEADER_RANGE = 'A1:L1';

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
        $sheet->setAutoFilter('A1:L'.$lastRow);
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
            'E' => 16, // Data da Compra
            'F' => 22, // Movimento
            'G' => 24, // Fonte
            'H' => 28, // Cartão/Fatura
            'I' => 12, // Parcela
            'J' => 16, // Tipo
            'K' => 16, // Pagamento
            'L' => 16, // Vencimento
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

        $sheet->getStyle('A2:L'.$lastRow)->applyFromArray([
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
                $sheet->getStyle(sprintf('A%d:L%d', $row, $row))->applyFromArray([
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
        $this->applyDateAlignment($sheet, $lastRow, 'E');
        $this->applyOccurrenceAlignment($sheet, $lastRow);
        $this->applySourceAlignment($sheet, $lastRow);
        $this->applyStatementAlignment($sheet, $lastRow);
        $this->applyInstallmentAlignment($sheet, $lastRow);
        $this->applyTypeAlignment($sheet, $lastRow);
        $this->applyDateAlignment($sheet, $lastRow, 'L');
        $this->applyDateAlignment($sheet, $lastRow, 'K');
    }

    private function applyDescriptionAlignment(Worksheet $sheet, int $lastRow): void
    {
        $sheet->getStyle('A2:A'.$lastRow)
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT);
    }

    private function applyValueAlignment(Worksheet $sheet, int $lastRow): void
    {
        $sheet->getStyle('B2:B'.$lastRow)->applyFromArray([
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_RIGHT,
            ],
        ]);
    }

    private function applyStatusAlignment(Worksheet $sheet, int $lastRow): void
    {
        $sheet->getStyle('C2:C'.$lastRow)
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    private function applyCategoryAlignment(Worksheet $sheet, int $lastRow): void
    {
        $sheet->getStyle('D2:D'.$lastRow)
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT);
    }

    private function applyTypeAlignment(Worksheet $sheet, int $lastRow): void
    {
        $sheet->getStyle('E2:E'.$lastRow)
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    private function applyOccurrenceAlignment(Worksheet $sheet, int $lastRow): void
    {
        $sheet->getStyle('F2:F'.$lastRow)
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT);
    }

    private function applySourceAlignment(Worksheet $sheet, int $lastRow): void
    {
        $sheet->getStyle('G2:G'.$lastRow)
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT);
    }

    private function applyStatementAlignment(Worksheet $sheet, int $lastRow): void
    {
        $sheet->getStyle('H2:H'.$lastRow)
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT);
    }

    private function applyInstallmentAlignment(Worksheet $sheet, int $lastRow): void
    {
        $sheet->getStyle('I2:I'.$lastRow)
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    private function applyDateAlignment(Worksheet $sheet, int $lastRow, string $column): void
    {
        $sheet->getStyle($column.'2:'.$column.$lastRow)
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    private function applyCellFormats(Worksheet $sheet, int $lastRow): void
    {
        if ($lastRow < 2) {
            return;
        }

        $sheet->getStyle('B2:B'.$lastRow)
            ->getNumberFormat()
            ->setFormatCode('[$R$-416] #,##0.00');

        $sheet->getStyle('E2:E'.$lastRow)
            ->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_DATE_DDMMYYYY);

        $sheet->getStyle('K2:L'.$lastRow)
            ->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_DATE_DDMMYYYY);
    }

    /** @param array<int, object{status: string, type: string, occurrence_type: string}> $rawData */
    private function applyConditionalFormatting(Worksheet $sheet, array $rawData): void
    {
        foreach ($rawData as $index => $row) {
            $rowNum = $index + 2;

            $this->formatStatus($sheet, $rowNum, $row->status);
            $this->formatType($sheet, $rowNum, $row->type);
            $this->formatOccurrence($sheet, $rowNum, $row->occurrence_type);
        }
    }

    private function formatStatus(Worksheet $sheet, int $rowNum, string $status): void
    {
        if ($status === 'paid') {
            $sheet->getStyle('C'.$rowNum)->applyFromArray([
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => '047857'],
                ],
            ]);
        } elseif ($status === 'pending') {
            $sheet->getStyle('C'.$rowNum)->applyFromArray([
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'B45309'],
                ],
            ]);
        } elseif ($status === 'overdue') {
            $sheet->getStyle('C'.$rowNum)->applyFromArray([
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
        $sheet->getStyle('J'.$rowNum)->applyFromArray([
            'font' => [
                'color' => ['rgb' => '1D4ED8'],
            ],
        ]);

        $sheet->getStyle('B'.$rowNum)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => '1D4ED8'],
            ],
        ]);
    }

    private function formatAsExpense(Worksheet $sheet, int $rowNum): void
    {
        $sheet->getStyle('J'.$rowNum)->applyFromArray([
            'font' => [
                'color' => ['rgb' => '334155'],
            ],
        ]);
    }

    private function formatOccurrence(Worksheet $sheet, int $rowNum, string $occurrenceType): void
    {
        if ($occurrenceType === Expense::OCCURRENCE_PURCHASE) {
            $sheet->getStyle('F'.$rowNum)->applyFromArray([
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => '7C3AED'],
                ],
            ]);

            return;
        }

        if ($occurrenceType === Expense::OCCURRENCE_INVOICE_PAYMENT) {
            $sheet->getStyle('F'.$rowNum)->applyFromArray([
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'C2410C'],
                ],
            ]);
        }
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
            'Data da Compra',
            'Movimento',
            'Fonte',
            'Cartão / Fatura',
            'Parcela',
            'Tipo',
            'Data de Pagamento',
            'Vencimento',
        ];
    }

    /**
     * @return array<int, object{
     *     title: string,
     *     amount: int|string,
     *     status: string,
     *     category: string|null,
     *     type: string,
     *     source_name: string|null,
     *     source_type: string|null,
     *     occurrence_type: string,
     *     card_source_name: string|null,
     *     installment_number: int|string|null,
     *     installment_total: int|string|null,
     *     purchase_date: string|null,
     *     payment_date: string|null,
     *     due_date: string|null,
     *     statement_reference_month: string|null
     * }>
     */
    private function getValues(): array
    {
        $values = DB::table('expenses')
            ->where('expenses.user_id', $this->getUserId())
            ->leftJoin('categories', 'expenses.category_id', '=', 'categories.id')
            ->leftJoin('sources as expense_sources', 'expenses.source_id', '=', 'expense_sources.id')
            ->leftJoin('credit_card_statements', 'expenses.credit_card_statement_id', '=', 'credit_card_statements.id')
            ->leftJoin('sources as card_sources', 'credit_card_statements.source_id', '=', 'card_sources.id')
            ->latest('expenses.created_at')
            ->select(
                'expenses.title',
                'expenses.amount',
                'expenses.status',
                'categories.name as category',
                'expenses.type',
                'expense_sources.name as source_name',
                'expense_sources.type as source_type',
                'expenses.occurrence_type',
                'card_sources.name as card_source_name',
                'expenses.installment_number',
                'expenses.installment_total',
                'expenses.purchase_date',
                'expenses.payment_date',
                'expenses.due_date',
                'credit_card_statements.reference_month as statement_reference_month',
            )
            ->get()
            ->toArray();

        /** @var array<int, object{
         *     title: string,
         *     amount: int|string,
         *     status: string,
         *     category: string|null,
         *     type: string,
         *     source_name: string|null,
         *     source_type: string|null,
         *     occurrence_type: string,
         *     card_source_name: string|null,
         *     installment_number: int|string|null,
         *     installment_total: int|string|null,
         *     purchase_date: string|null,
         *     payment_date: string|null,
         *     due_date: string|null,
         *     statement_reference_month: string|null
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
     *     source_name: string|null,
     *     source_type: string|null,
     *     occurrence_type: string,
     *     card_source_name: string|null,
     *     installment_number: int|string|null,
     *     installment_total: int|string|null,
     *     purchase_date: string|null,
     *     payment_date: string|null,
     *     due_date: string|null,
     *     statement_reference_month: string|null
     * }> $values
     * @return list<list<string|float|null>>
     */
    private function normalizeValues(array $values): array
    {
        return array_values(array_map(fn (object $row): array => [
            $row->title,
            $this->normalizeMoney((int) $row->amount),
            $this->translateStatus($row->status),
            $row->category,
            $this->toExcelDate($row->purchase_date),
            $this->translateOccurrence($row->occurrence_type),
            $row->source_name,
            $this->formatStatementContext($row),
            $this->formatInstallment($row->installment_number, $row->installment_total),
            $this->translateType($row->type),
            $this->toExcelDate($row->payment_date),
            $this->toExcelDate($row->due_date),
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

        $excelDate = ExcelDate::PHPToExcel(Date::parse($date));

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

    private function translateOccurrence(string $occurrenceType): string
    {
        return match ($occurrenceType) {
            Expense::OCCURRENCE_PURCHASE => 'Compra no cartão',
            Expense::OCCURRENCE_INVOICE_PAYMENT => 'Pagamento de fatura',
            Expense::OCCURRENCE_DIRECT => 'Lançamento direto',
            default => ucfirst($occurrenceType),
        };
    }

    private function formatStatementContext(object $row): ?string
    {
        if (! isset($row->statement_reference_month) || ! is_string($row->statement_reference_month)) {
            return null;
        }

        $cardSourceName = $row->card_source_name ?? null;
        if (! is_string($cardSourceName) || $cardSourceName === '') {
            $cardSourceName = $row->source_type === Source::TYPE_CREDIT_CARD
                ? ($row->source_name ?? null)
                : null;
        }

        $referenceMonth = Date::parse($row->statement_reference_month)->format('m/Y');

        if ($cardSourceName === null || $cardSourceName === '') {
            return $referenceMonth;
        }

        return $cardSourceName.' - '.$referenceMonth;
    }

    private function formatInstallment(int|string|null $installmentNumber, int|string|null $installmentTotal): ?string
    {
        if ($installmentNumber === null || $installmentTotal === null) {
            return null;
        }

        return (int) $installmentNumber.'/'.(int) $installmentTotal;
    }
}
