<?php

declare(strict_types=1);

namespace App\Strategy;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use stdClass;

class CsvExportStrategy implements ExportStrategyInterface
{
    public function execute(): StreamedResponse
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            throw new \RuntimeException('Usuário autenticado não encontrado.');
        }

        $name = Str::slug($user->name);

        $fileName = $name . '-fillament-wallet-'.Str::uuid().'.csv';

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
            if ($file === false) {
                throw new \RuntimeException('Não foi possível abrir o stream de saída.');
            }

            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, [
                'TITLE',
                'AMOUNT',
                'STATUS',
                'TYPE',
                'PAYMENT_DATE',
                'DUE_DATE',
                'CREATED_AT',
                'CATEGORY_NAME',
                'SOURCE_NAME',
            ], ';');

            DB::table('expenses')
                ->leftJoin('categories', 'categories.id', '=', 'expenses.category_id')
                ->leftJoin('sources', 'sources.id', '=', 'expenses.source_id')
                ->where('expenses.user_id', $userId)
                ->select(
                    'expenses.title',
                    'expenses.amount',
                    'expenses.status',
                    'expenses.type',
                    'expenses.payment_date',
                    'expenses.due_date',
                    'expenses.created_at',
                    'categories.name as category_name',
                    'sources.name as source_name',
                )
                ->latest('expenses.created_at')
                ->chunk(1000, function ($expenses) use ($file): void {
                    foreach ($expenses as $expense) {
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
                        ];

                        fputcsv($file, $fields, ';');
                    }
                });

            fclose($file);
        };
    }
}
