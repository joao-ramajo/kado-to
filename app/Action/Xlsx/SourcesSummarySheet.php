<?php

declare(strict_types=1);

namespace App\Action\Xlsx;

use App\Domain\Interfaces\XlsxSheet;
use App\Models\CreditCardStatement;
use App\Models\Expense;
use App\Models\Source;
use DateTimeInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SourcesSummarySheet implements XlsxSheet
{
    private const HEADER_BG = 'F3F4F6';

    private const HEADER_FONT = '111827';

    private const TABLE_BORDER_COLOR = 'D1D5DB';

    private const STRIPE_BG = 'F9FAFB';

    public function addTo(Spreadsheet $spreadsheet): void
    {
        $sheet = new Worksheet($spreadsheet, 'Resumo Financeiro');
        $spreadsheet->addSheet($sheet);

        $this->setupColumnWidths($sheet);
        $nextRow = $this->renderSourcesSection($sheet);
        $this->renderStatementsSection($sheet, $nextRow + 2);
    }

    private function getUserId(): int
    {
        $userId = Auth::id();

        if (is_int($userId)) {
            return $userId;
        }

        return 0;
    }

    private function setupColumnWidths(Worksheet $sheet): void
    {
        $sheet->getColumnDimension('A')->setWidth(28);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(18);
        $sheet->getColumnDimension('D')->setWidth(18);
        $sheet->getColumnDimension('E')->setWidth(18);
        $sheet->getColumnDimension('F')->setWidth(24);
    }

    private function renderSourcesSection(Worksheet $sheet): int
    {
        $sheet->setCellValue('A1', 'Totais por Fonte');
        $sheet->fromArray([
            ['Fonte', 'Total ganho', 'Total gasto', 'Saldo final'],
        ], null, 'A2');
        $rows = $this->getSourceRows();
        if ($rows !== []) {
            $sheet->fromArray($rows, null, 'A3');
        }

        $lastRow = max(3, count($rows) + 2);

        $this->applySectionTitleStyle($sheet, 'A1:D1');
        $this->applyHeaderStyles($sheet, 'A2:D2');
        $this->applyBodyStyles($sheet, 'A3:D'.$lastRow);
        $sheet->getStyle('B3:D'.$lastRow)
            ->getNumberFormat()
            ->setFormatCode('[$R$-416] #,##0.00');

        return $lastRow;
    }

    /**
     * @return list<list<string|float|null>>
     */
    private function getSourceRows(): array
    {
        $rows = Source::query()
            ->where('user_id', $this->getUserId())
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        /** @var list<list<string|float|null>> */
        return $rows->map(function (Source $source): array {
            $income = (int) Expense::query()
                ->where('source_id', $source->id)
                ->where('type', 'income')
                ->sum('amount');

            $expenseQuery = Expense::query()
                ->where('source_id', $source->id)
                ->where('type', 'expense');

            if ($source->type === Source::TYPE_CREDIT_CARD) {
                $expenseQuery->where('occurrence_type', Expense::OCCURRENCE_PURCHASE);
            } else {
                $expenseQuery->where('occurrence_type', '!=', Expense::OCCURRENCE_PURCHASE);
            }

            $expense = (int) $expenseQuery->sum('amount');
            $finalBalance = $source->type === Source::TYPE_CREDIT_CARD
                ? $this->normalizeMoney(((int) $source->credit_limit) - $expense)
                : $this->normalizeMoney($income - $expense);

            return [
                $source->name,
                $this->normalizeMoney($income),
                $this->normalizeMoney($expense),
                $finalBalance,
            ];
        })->values()->all();
    }

    private function renderStatementsSection(Worksheet $sheet, int $startRow): void
    {
        $sheet->setCellValue('A'.$startRow, 'Cartões e Faturas');
        $sheet->fromArray([
            ['Cartão', 'Referência', 'Status da fatura', 'Valor da fatura', 'Pago em', 'Fonte de pagamento'],
        ], null, 'A'.($startRow + 1));

        $rows = $this->getStatementRows();
        $dataStartRow = $startRow + 2;

        if ($rows !== []) {
            $sheet->fromArray($rows, null, 'A'.$dataStartRow);
        }

        $lastRow = max($dataStartRow, count($rows) + $startRow + 1);

        $this->applySectionTitleStyle($sheet, 'A'.$startRow.':F'.$startRow);
        $this->applyHeaderStyles($sheet, 'A'.($startRow + 1).':F'.($startRow + 1));
        $this->applyBodyStyles($sheet, 'A'.$dataStartRow.':F'.$lastRow);
        $sheet->getStyle('D'.$dataStartRow.':D'.$lastRow)
            ->getNumberFormat()
            ->setFormatCode('[$R$-416] #,##0.00');
        $sheet->getStyle('E'.$dataStartRow.':E'.$lastRow)
            ->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_DATE_DDMMYYYY);
        $sheet->setAutoFilter('A'.($startRow + 1).':F'.$lastRow);
        $sheet->freezePane('A'.$dataStartRow);
    }

    /**
     * @return list<list<string|float|null>>
     */
    private function getStatementRows(): array
    {
        $rows = CreditCardStatement::query()
            ->join('sources as card_sources', 'credit_card_statements.source_id', '=', 'card_sources.id')
            ->leftJoin('sources as payment_sources', 'credit_card_statements.payment_source_id', '=', 'payment_sources.id')
            ->where('card_sources.user_id', $this->getUserId())
            ->orderByDesc('credit_card_statements.reference_month')
            ->select(
                'card_sources.name as card_name',
                'credit_card_statements.reference_month',
                'credit_card_statements.status',
                'credit_card_statements.total_amount',
                'credit_card_statements.paid_at',
                'payment_sources.name as payment_source_name',
            )
            ->get();

        /** @var list<list<string|float|null>> */
        return $rows->map(function (object $row): array {
            return [
                (string) $row->card_name,
                $this->formatReferenceMonth((string) $row->reference_month),
                $this->translateStatementStatus((string) $row->status),
                $this->normalizeMoney((int) $row->total_amount),
                $this->toExcelDate($row->paid_at),
                is_string($row->payment_source_name) ? $row->payment_source_name : null,
            ];
        })->values()->all();
    }

    private function applySectionTitleStyle(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12,
                'color' => ['rgb' => self::HEADER_FONT],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E5E7EB'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
    }

    private function applyHeaderStyles(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
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

    private function applyBodyStyles(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
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

        [$start, $end] = explode(':', $range);
        $startRow = (int) preg_replace('/\D/', '', $start);
        $endRow = (int) preg_replace('/\D/', '', $end);
        $startColumn = preg_replace('/\d/', '', $start) ?: 'A';
        $endColumn = preg_replace('/\d/', '', $end) ?: 'A';

        for ($row = $startRow; $row <= $endRow; $row++) {
            if ($row % 2 === 0) {
                $sheet->getStyle(sprintf('%s%d:%s%d', $startColumn, $row, $endColumn, $row))->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => self::STRIPE_BG],
                    ],
                ]);
            }
        }
    }

    private function normalizeMoney(int|string $amount): float
    {
        return ((int) $amount) / 100;
    }

    private function formatReferenceMonth(string $referenceMonth): string
    {
        return date('m/Y', strtotime($referenceMonth));
    }

    private function translateStatementStatus(string $status): string
    {
        return match ($status) {
            CreditCardStatement::STATUS_OPEN => 'Aberta',
            CreditCardStatement::STATUS_CLOSED => 'Fechada',
            CreditCardStatement::STATUS_PAID => 'Paga',
            default => ucfirst($status),
        };
    }

    private function toExcelDate(mixed $date): ?float
    {
        if ($date instanceof DateTimeInterface) {
            $timestamp = $date->getTimestamp();
        } elseif (is_string($date) && trim($date) !== '') {
            $timestamp = strtotime($date);
        } else {
            $timestamp = false;
        }

        if ($timestamp === false) {
            return null;
        }

        return 25569 + ($timestamp / 86400);
    }
}
