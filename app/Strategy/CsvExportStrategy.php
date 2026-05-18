<?php

declare(strict_types=1);

namespace App\Strategy;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExportStrategy implements ExportStrategyInterface
{
    /** @var list<string> */
    private const HEADERS = [
        'TITLE',
        'AMOUNT',
        'STATUS',
        'TYPE',
        'PAYMENT_DATE',
        'DUE_DATE',
        'CREATED_AT',
        'CATEGORY_NAME',
        'SOURCE_NAME',
        'SOURCE_TYPE',
        'SOURCE_COLOR',
        'SOURCE_ALLOW_NEGATIVE',
        'SOURCE_CREDIT_LIMIT',
        'SOURCE_STATEMENT_CLOSING_DAY',
        'SOURCE_STATEMENT_DUE_DAY',
        'ORIGIN_TYPE',
        'OCCURRENCE_TYPE',
        'PURCHASE_DATE',
        'INSTALLMENT_GROUP_ID',
        'INSTALLMENT_NUMBER',
        'INSTALLMENT_TOTAL',
        'CARD_SOURCE_NAME',
        'CARD_SOURCE_COLOR',
        'CARD_SOURCE_CREDIT_LIMIT',
        'CARD_SOURCE_STATEMENT_CLOSING_DAY',
        'CARD_SOURCE_STATEMENT_DUE_DAY',
        'STATEMENT_REFERENCE_MONTH',
        'STATEMENT_CLOSING_AT',
        'STATEMENT_DUE_AT',
        'STATEMENT_PAID_AT',
    ];

    public function execute(): StreamedResponse
    {
        $user = Auth::user();
        throw_unless($user instanceof User, RuntimeException::class, 'Usuário autenticado não encontrado.');

        $name = Str::slug($user->name);

        $fileName = $name.'-fillament-wallet-'.Str::uuid().'.csv';

        $callback = $this->generate($user->id);

        return response()->streamDownload($callback, $fileName, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $fileName),
        ]);
    }

    public function generate(int $userId): callable
    {
        return function () use ($userId): void {
            $file = fopen('php://output', 'w');
            throw_if($file === false, RuntimeException::class, 'Não foi possível abrir o stream de saída.');

            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, self::HEADERS, ';');

            DB::table('expenses')
                ->leftJoin('categories', 'categories.id', '=', 'expenses.category_id')
                ->leftJoin('sources as expense_sources', 'expense_sources.id', '=', 'expenses.source_id')
                ->leftJoin('credit_card_statements', 'credit_card_statements.id', '=', 'expenses.credit_card_statement_id')
                ->leftJoin('sources as card_sources', 'card_sources.id', '=', 'credit_card_statements.source_id')
                ->where('expenses.user_id', $userId)
                ->select(
                    'expenses.title',
                    'expenses.amount',
                    'expenses.status',
                    'expenses.type',
                    'expenses.origin_type',
                    'expenses.occurrence_type',
                    'expenses.payment_date',
                    'expenses.purchase_date',
                    'expenses.due_date',
                    'expenses.created_at',
                    'expenses.installment_group_id',
                    'expenses.installment_number',
                    'expenses.installment_total',
                    'categories.name as category_name',
                    'expense_sources.name as source_name',
                    'expense_sources.type as source_type',
                    'expense_sources.color as source_color',
                    'expense_sources.allow_negative as source_allow_negative',
                    'expense_sources.credit_limit as source_credit_limit',
                    'expense_sources.statement_closing_day as source_statement_closing_day',
                    'expense_sources.statement_due_day as source_statement_due_day',
                    'card_sources.name as card_source_name',
                    'card_sources.color as card_source_color',
                    'card_sources.credit_limit as card_source_credit_limit',
                    'card_sources.statement_closing_day as card_source_statement_closing_day',
                    'card_sources.statement_due_day as card_source_statement_due_day',
                    'credit_card_statements.reference_month as statement_reference_month',
                    'credit_card_statements.closing_at as statement_closing_at',
                    'credit_card_statements.due_at as statement_due_at',
                    'credit_card_statements.paid_at as statement_paid_at',
                )
                ->latest('expenses.created_at')
                ->chunk(1000, function ($expenses) use ($file): void {
                    foreach ($expenses as $expense) {
                        $cardSourceName = $expense->occurrence_type === Expense::OCCURRENCE_INVOICE_PAYMENT
                            ? $expense->card_source_name
                            : ($expense->source_type === 'credit_card' ? $expense->source_name : null);

                        /** @var array<int, bool|float|int|string|null> $fields */
                        $fields = [
                            $expense->title ?? '-',
                            $expense->amount,
                            $expense->status ?? '-',
                            $expense->type ?? '-',
                            $expense->payment_date ?? '-',
                            $expense->due_date ?? '-',
                            $expense->created_at ?? '-',
                            $expense->category_name ?? '-',
                            $expense->source_name ?? '-',
                            $expense->source_type ?? '-',
                            $expense->source_color ?? '-',
                            $expense->source_allow_negative ? '1' : '0',
                            $expense->source_credit_limit ?? '-',
                            $expense->source_statement_closing_day ?? '-',
                            $expense->source_statement_due_day ?? '-',
                            $expense->origin_type ?? Expense::ORIGIN_DIRECT,
                            $expense->occurrence_type ?? Expense::OCCURRENCE_DIRECT,
                            $expense->purchase_date ?? '-',
                            $expense->installment_group_id ?? '-',
                            $expense->installment_number ?? '-',
                            $expense->installment_total ?? '-',
                            $cardSourceName ?? '-',
                            $expense->card_source_color ?? '-',
                            $expense->card_source_credit_limit ?? '-',
                            $expense->card_source_statement_closing_day ?? '-',
                            $expense->card_source_statement_due_day ?? '-',
                            $expense->statement_reference_month ?? '-',
                            $expense->statement_closing_at ?? '-',
                            $expense->statement_due_at ?? '-',
                            $expense->statement_paid_at ?? '-',
                        ];

                        fputcsv($file, $fields, ';');
                    }
                });

            fclose($file);
        };
    }
}
