<?php

declare(strict_types=1);

namespace App\Action\Expense;

use App\Models\Expense;
use App\Support\CreditCard\CreditCardStatementService;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

class UpdateExpenseAction
{
    public function __construct(
        private readonly CreditCardStatementService $creditCardStatementService,
    ) {}

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
        DB::transaction(function () use ($data, $id): void {
            $expense = Expense::query()->findOrFail($id);

            throw_if($expense->user_id !== Auth::id(), DomainException::class, 'Você não pode alterar este registro');

            throw_if($expense->occurrence_type === Expense::OCCURRENCE_INVOICE_PAYMENT, DomainException::class, 'Registros de pagamento de fatura devem ser gerenciados pelo fluxo da fatura.');

            $isCreditCardPurchase = $expense->origin_type === Expense::ORIGIN_CREDIT_CARD
                && $expense->occurrence_type === Expense::OCCURRENCE_PURCHASE;

            if ($isCreditCardPurchase) {
                $sourceIdWasProvided = array_key_exists('source_id', $data);

                throw_if($data['type'] !== $expense->type, DomainException::class, 'Compras no cartão não podem alterar o tipo do registro.');
                throw_if($data['status'] !== $expense->status, DomainException::class, 'Compras no cartão não podem ter o status alterado manualmente.');
                throw_if(
                    $sourceIdWasProvided && $data['source_id'] !== $expense->source_id,
                    DomainException::class,
                    'Compras no cartão devem continuar na mesma fonte.'
                );

                $data['type'] = $expense->type;
                $data['status'] = $expense->status;
                $data['payment_date'] = $expense->payment_date?->startOfDay();
                $data['purchase_date'] = $expense->purchase_date?->toDateString();
                $data['source_id'] = $expense->source_id;
                $data['origin_type'] = $expense->origin_type;
                $data['occurrence_type'] = $expense->occurrence_type;
                $data['credit_card_statement_id'] = $expense->credit_card_statement_id;
                $data['installment_group_id'] = $expense->installment_group_id;
                $data['installment_number'] = $expense->installment_number;
                $data['installment_total'] = $expense->installment_total;
                $data['due_date'] = $expense->due_date?->toDateString();

                $expense->update($data);

                if ($expense->credit_card_statement_id !== null) {
                    $this->creditCardStatementService->syncById($expense->credit_card_statement_id);
                }

                return;
            }

            if ($data['status'] === 'paid') {
                $paymentDate = isset($data['payment_date'])
                    ? Date::createFromFormat('Y-m-d', $data['payment_date'])
                    : null;
                $data['payment_date'] = $paymentDate?->startOfDay() ?? now();
            }

            $data['payment_date'] ??= null;

            $expense->update($data);
        });
    }
}
