<?php

declare(strict_types=1);

namespace App\Action\Xlsx;

use App\Domain\Interfaces\XlsxSheet;
use App\Models\CreditCardStatement;
use App\Models\Expense;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use stdClass;

class InsightsSheet implements XlsxSheet
{
    private const HEADER_FONT = '111827';

    private const HEADER_BG = 'F3F4F6';

    private const SECTION_BG = 'E5E7EB';

    private const STRIPE_BG = 'F9FAFB';

    private const TABLE_BORDER_COLOR = 'D1D5DB';

    public function addTo(Spreadsheet $spreadsheet): void
    {
        $sheet = new Worksheet($spreadsheet, 'Insights');
        $spreadsheet->addSheet($sheet);

        $sheet->getColumnDimension('A')->setWidth(32);
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(52);

        $sheet->setCellValue('A1', 'Insights');
        $sheet->fromArray([
            ['Insight', 'Valor', 'Contexto'],
        ], null, 'A2');
        $sheet->fromArray($this->buildInsightRows(), null, 'A3');

        $this->applySectionTitleStyle($sheet, 'A1:C1');
        $this->applyHeaderStyles($sheet, 'A2:C2');
        $this->applyBodyStyles($sheet, 'A3:C8');
        $sheet->setAutoFilter('A2:C8');
        $sheet->freezePane('A3');
    }

    private function getUserId(): int
    {
        $userId = Auth::id();

        return is_int($userId) ? $userId : 0;
    }

    /**
     * @return list<list<string|null>>
     */
    private function buildInsightRows(): array
    {
        $userId = $this->getUserId();
        $now = now();
        $windowStart = $now->copy()->subDays(29)->startOfDay();

        return [
            $this->largestRecentPaidExpense($userId, $windowStart, $now),
            $this->mostFrequentCategory($userId, $windowStart, $now),
            $this->highestValueCategory($userId, $windowStart, $now),
            $this->mostUsedSource($userId, $windowStart, $now),
            $this->openCardAmount($userId),
            $this->averageDailyPaidExpense($userId, $windowStart, $now),
        ];
    }

    /**
     * @return list<string|null>
     */
    private function largestRecentPaidExpense(int $userId, $windowStart, $now): array
    {
        $expense = DB::table('expenses')
            ->join('sources', 'expenses.source_id', '=', 'sources.id')
            ->where('expenses.user_id', $userId)
            ->where('expenses.type', 'expense')
            ->where('expenses.status', 'paid')
            ->where('sources.type', 'cash_like')
            ->where('expenses.occurrence_type', '!=', Expense::OCCURRENCE_PURCHASE)
            ->where(function ($query) use ($windowStart, $now): void {
                $query
                    ->whereBetween('expenses.payment_date', [$windowStart, $now->copy()->endOfDay()])
                    ->orWhereBetween('expenses.created_at', [$windowStart, $now->copy()->endOfDay()]);
            })
            ->select(
                'expenses.title',
                'expenses.amount',
                'expenses.payment_date',
                'sources.name as source_name',
            )
            ->orderByDesc('expenses.amount')
            ->orderByDesc('expenses.payment_date')
            ->first();

        if (! $expense instanceof stdClass) {
            return $this->emptyInsightRow('Maior gasto pago recente');
        }

        return [
            'Maior gasto pago recente',
            $this->formatMoney((int) $expense->amount),
            sprintf(
                '%s em %s via %s',
                (string) $expense->title,
                $this->formatDate((string) $expense->payment_date),
                (string) $expense->source_name,
            ),
        ];
    }

    /**
     * @return list<string|null>
     */
    private function mostFrequentCategory(int $userId, $windowStart, $now): array
    {
        $row = DB::table('expenses')
            ->join('categories', 'expenses.category_id', '=', 'categories.id')
            ->where('expenses.user_id', $userId)
            ->where('expenses.type', 'expense')
            ->where('expenses.occurrence_type', '!=', Expense::OCCURRENCE_INVOICE_PAYMENT)
            ->where(function ($query) use ($windowStart, $now): void {
                $query
                    ->whereBetween('expenses.created_at', [$windowStart, $now->copy()->endOfDay()])
                    ->orWhereBetween('expenses.purchase_date', [$windowStart->toDateString(), $now->toDateString()]);
            })
            ->selectRaw('categories.name as category_name, COUNT(*) as total_items')
            ->groupBy('categories.name')
            ->orderByDesc('total_items')
            ->orderBy('categories.name')
            ->first();

        if (! $row instanceof stdClass) {
            return $this->emptyInsightRow('Categoria mais frequente');
        }

        return [
            'Categoria mais frequente',
            (string) $row->category_name,
            sprintf('%d lançamentos nos últimos 30 dias', (int) $row->total_items),
        ];
    }

    /**
     * @return list<string|null>
     */
    private function highestValueCategory(int $userId, $windowStart, $now): array
    {
        $row = DB::table('expenses')
            ->join('categories', 'expenses.category_id', '=', 'categories.id')
            ->where('expenses.user_id', $userId)
            ->where('expenses.type', 'expense')
            ->where('expenses.occurrence_type', '!=', Expense::OCCURRENCE_INVOICE_PAYMENT)
            ->where(function ($query) use ($windowStart, $now): void {
                $query
                    ->whereBetween('expenses.created_at', [$windowStart, $now->copy()->endOfDay()])
                    ->orWhereBetween('expenses.purchase_date', [$windowStart->toDateString(), $now->toDateString()]);
            })
            ->selectRaw('categories.name as category_name, SUM(expenses.amount) as total_amount')
            ->groupBy('categories.name')
            ->orderByDesc('total_amount')
            ->orderBy('categories.name')
            ->first();

        if (! $row instanceof stdClass) {
            return $this->emptyInsightRow('Categoria de maior valor');
        }

        return [
            'Categoria de maior valor',
            (string) $row->category_name,
            sprintf('%s consumidos nos últimos 30 dias', $this->formatMoney((int) $row->total_amount)),
        ];
    }

    /**
     * @return list<string|null>
     */
    private function mostUsedSource(int $userId, $windowStart, $now): array
    {
        $row = DB::table('expenses')
            ->join('sources', 'expenses.source_id', '=', 'sources.id')
            ->where('expenses.user_id', $userId)
            ->where('expenses.type', 'expense')
            ->where('expenses.occurrence_type', '!=', Expense::OCCURRENCE_INVOICE_PAYMENT)
            ->where(function ($query) use ($windowStart, $now): void {
                $query
                    ->whereBetween('expenses.created_at', [$windowStart, $now->copy()->endOfDay()])
                    ->orWhereBetween('expenses.purchase_date', [$windowStart->toDateString(), $now->toDateString()]);
            })
            ->selectRaw('sources.name as source_name, COUNT(*) as total_items')
            ->groupBy('sources.name')
            ->orderByDesc('total_items')
            ->orderBy('sources.name')
            ->first();

        if (! $row instanceof stdClass) {
            return $this->emptyInsightRow('Fonte mais usada');
        }

        return [
            'Fonte mais usada',
            (string) $row->source_name,
            sprintf('%d lançamentos nos últimos 30 dias', (int) $row->total_items),
        ];
    }

    /**
     * @return list<string|null>
     */
    private function openCardAmount(int $userId): array
    {
        $openStatements = CreditCardStatement::query()
            ->join('sources', 'credit_card_statements.source_id', '=', 'sources.id')
            ->where('sources.user_id', $userId)
            ->where('credit_card_statements.status', '!=', CreditCardStatement::STATUS_PAID)
            ->select(
                'credit_card_statements.total_amount',
            )
            ->get();

        if ($openStatements->isEmpty()) {
            return $this->emptyInsightRow('Cartão em aberto');
        }

        $totalAmount = (int) $openStatements->sum('total_amount');

        return [
            'Cartão em aberto',
            $this->formatMoney($totalAmount),
            sprintf('%d faturas não pagas', $openStatements->count()),
        ];
    }

    /**
     * @return list<string|null>
     */
    private function averageDailyPaidExpense(int $userId, $windowStart, $now): array
    {
        $totalAmount = (int) Expense::query()
            ->join('sources', 'expenses.source_id', '=', 'sources.id')
            ->where('expenses.user_id', $userId)
            ->where('expenses.type', 'expense')
            ->where('expenses.status', 'paid')
            ->where('sources.type', 'cash_like')
            ->where('expenses.occurrence_type', '!=', Expense::OCCURRENCE_PURCHASE)
            ->where(function ($query) use ($windowStart, $now): void {
                $query
                    ->whereBetween('expenses.payment_date', [$windowStart, $now->copy()->endOfDay()])
                    ->orWhereBetween('expenses.created_at', [$windowStart, $now->copy()->endOfDay()]);
            })
            ->sum('expenses.amount');

        if ($totalAmount === 0) {
            return $this->emptyInsightRow('Média diária de gasto pago');
        }

        return [
            'Média diária de gasto pago',
            $this->formatMoney((int) round($totalAmount / 30)),
            sprintf('%s pagos em 30 dias', $this->formatMoney($totalAmount)),
        ];
    }

    /**
     * @return list<string|null>
     */
    private function emptyInsightRow(string $title): array
    {
        return [$title, null, 'Sem dados suficientes'];
    }

    private function formatMoney(int $amount): string
    {
        return 'R$ '.number_format($amount / 100, 2, ',', '.');
    }

    private function formatDate(string $date): string
    {
        return date('d/m/Y', strtotime($date));
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
                'startColor' => ['rgb' => self::SECTION_BG],
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

        foreach (range(3, 8) as $row) {
            if ($row % 2 === 0) {
                $sheet->getStyle(sprintf('A%d:C%d', $row, $row))->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => self::STRIPE_BG],
                    ],
                ]);
            }
        }
    }
}
