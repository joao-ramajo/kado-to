<?php

declare(strict_types=1);

namespace App\Action\Expense;

use App\Models\Expense;
use App\Models\Source;
use App\Support\CreditCard\CreditCardStatementService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateExpense
{
    public function __construct(
        private readonly CreditCardStatementService $creditCardStatementService,
    ) {}

    public function execute(array $data): void
    {
        DB::transaction(function () use ($data) {
            $source = $this->resolveSource($data['userId'], $data['source_id'] ?? null);

            if ($source->isCreditCard()) {
                $this->createCreditCardPurchase($source, $data);

                return;
            }

            $this->createDirectExpense($source, $data);
        });
    }

    private function resolveSource(int $userId, ?int $sourceId): Source
    {
        $query = Source::query()->where('user_id', $userId);

        if ($sourceId !== null) {
            $source = $query->where('id', $sourceId)->first();
        } else {
            $source = $query->where('is_default', true)->first();
        }

        if ($source === null) {
            throw new DomainException('Fonte não encontrada para este usuário.');
        }

        return $source;
    }

    private function createDirectExpense(Source $source, array $data): void
    {
        $paymentDate = null;

        if ($data['status'] === 'paid') {
            $paymentDate = isset($data['payment_date'])
                ? Carbon::createFromFormat('Y-m-d', $data['payment_date'])->startOfDay()
                : now();
        }

        Expense::create([
            'title' => $data['title'],
            'amount' => $data['amount'],
            'type' => $data['type'],
            'status' => $data['status'],
            'user_id' => $data['userId'],
            'category_id' => $data['category_id'] ?? null,
            'payment_date' => $paymentDate,
            'purchase_date' => $data['purchase_date'] ?? null,
            'source_id' => $source->id,
            'origin_type' => Expense::ORIGIN_DIRECT,
            'occurrence_type' => Expense::OCCURRENCE_DIRECT,
        ]);
    }

    private function createCreditCardPurchase(Source $source, array $data): void
    {
        if (($data['type'] ?? null) !== 'expense') {
            throw new DomainException('Cartão de crédito aceita apenas despesas.');
        }

        if (($data['status'] ?? null) === 'paid') {
            throw new DomainException('Compras no cartão devem ser quitadas pelo pagamento da fatura.');
        }

        $purchaseDate = isset($data['purchase_date'])
            ? CarbonImmutable::createFromFormat('Y-m-d', $data['purchase_date'])
            : CarbonImmutable::today();
        $installmentTotal = (int) ($data['installment_total'] ?? 1);
        $installmentAmounts = $this->creditCardStatementService->splitInstallments((int) $data['amount'], $installmentTotal);
        $groupId = (string) Str::uuid();
        $statementIds = [];

        foreach ($installmentAmounts as $index => $installmentAmount) {
            $installmentNumber = $index + 1;
            $installmentDate = $purchaseDate->addMonthsNoOverflow($index);
            $statement = $this->creditCardStatementService->resolveForInstallment($source, $installmentDate);

            Expense::create([
                'title' => $data['title'],
                'amount' => $installmentAmount,
                'type' => 'expense',
                'status' => 'pending',
                'user_id' => $data['userId'],
                'category_id' => $data['category_id'] ?? null,
                'payment_date' => null,
                'purchase_date' => $purchaseDate->toDateString(),
                'due_date' => $statement->due_at,
                'source_id' => $source->id,
                'origin_type' => Expense::ORIGIN_CREDIT_CARD,
                'occurrence_type' => Expense::OCCURRENCE_PURCHASE,
                'credit_card_statement_id' => $statement->id,
                'installment_group_id' => $groupId,
                'installment_number' => $installmentNumber,
                'installment_total' => $installmentTotal,
            ]);

            $statementIds[$statement->id] = true;
        }

        foreach (array_keys($statementIds) as $statementId) {
            $this->creditCardStatementService->syncById((int) $statementId);
        }
    }
}
