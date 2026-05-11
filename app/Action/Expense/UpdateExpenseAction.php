<?php

declare(strict_types=1);

namespace App\Action\Expense;

use Illuminate\Support\Facades\Date;
use App\Models\Expense;
use DomainException;
use Illuminate\Support\Facades\Auth;

class UpdateExpenseAction
{
    /**
     * @param array{
     *     title: string,
     *     amount: int,
     *     type: string,
     *     status: string,
     *     category_id?: int|null,
     *     source_id?: int|null,
     *     purchase_date?: string|null,
     *     payment_date?: string|null
     * } $data
     */
    public function execute(array $data, int $id): void
    {
        $expense = Expense::query()->findOrFail($id);

        throw_if($expense->user_id !== Auth::id(), DomainException::class, 'Você não pode alterar este registro');

        throw_if($expense->origin_type === Expense::ORIGIN_CREDIT_CARD || $expense->occurrence_type === Expense::OCCURRENCE_INVOICE_PAYMENT, DomainException::class, 'Registros de cartão devem ser gerenciados pelo fluxo da fatura.');

        if ($data['status'] === 'paid') {
            $data['payment_date'] = isset($data['payment_date'])
                ? Date::createFromFormat('Y-m-d', $data['payment_date'])->startOfDay()
                : now();
        } else {
            $data['payment_date'] = null;
        }

        $expense->update($data);
    }
}
