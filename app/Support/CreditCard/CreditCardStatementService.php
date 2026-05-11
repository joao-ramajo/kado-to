<?php

declare(strict_types=1);

namespace App\Support\CreditCard;

use DateTimeInterface;
use App\Models\CreditCardStatement;
use App\Models\Expense;
use App\Models\Source;
use Carbon\CarbonImmutable;
use DomainException;

class CreditCardStatementService
{
    public function resolveForInstallment(Source $source, CarbonImmutable $installmentDate): CreditCardStatement
    {
        throw_unless($source->isCreditCard(), DomainException::class, 'A fonte selecionada não é um cartão de crédito.');

        $referenceMonth = $this->resolveReferenceMonth($installmentDate, (int) $source->statement_closing_day);
        $closingAt = $referenceMonth->day(min((int) $source->statement_closing_day, $referenceMonth->daysInMonth));
        $dueAt = $referenceMonth->day(min((int) $source->statement_due_day, $referenceMonth->daysInMonth));

        $statement = CreditCardStatement::query()->firstOrCreate([
            'source_id' => $source->id,
            'reference_month' => $referenceMonth->toDateString(),
        ], [
            'closing_at' => $closingAt->toDateString(),
            'due_at' => $dueAt->toDateString(),
            'status' => $this->determineStatus(null, $closingAt),
            'total_amount' => 0,
        ]);

        return $this->sync($statement);
    }

    public function syncById(int $statementId): void
    {
        $statement = CreditCardStatement::query()->find($statementId);

        if ($statement !== null) {
            $this->sync($statement);
        }
    }

    public function sync(CreditCardStatement $statement): CreditCardStatement
    {
        $totalAmount = (int) Expense::query()->where('credit_card_statement_id', $statement->id)
            ->where('occurrence_type', Expense::OCCURRENCE_PURCHASE)
            ->sum('amount');

        $statement->fill([
            'total_amount' => $totalAmount,
            'status' => $this->determineStatus($statement->paid_at, CarbonImmutable::parse($statement->closing_at)),
        ]);
        $statement->save();

        return $statement->refresh();
    }

    /** @return list<int> */
    public function splitInstallments(int $amount, int $installmentTotal): array
    {
        throw_if($installmentTotal < 1, DomainException::class, 'A quantidade de parcelas deve ser maior que zero.');

        $baseAmount = intdiv($amount, $installmentTotal);
        $remainder = $amount % $installmentTotal;
        $parts = [];

        for ($index = 1; $index <= $installmentTotal; $index++) {
            $parts[] = $baseAmount + ($index <= $remainder ? 1 : 0);
        }

        return $parts;
    }

    private function resolveReferenceMonth(CarbonImmutable $installmentDate, int $closingDay): CarbonImmutable
    {
        $reference = $installmentDate->startOfMonth();

        if ($installmentDate->day > $closingDay) {
            return $reference->addMonthNoOverflow();
        }

        return $reference;
    }

    private function determineStatus(?DateTimeInterface $paidAt, CarbonImmutable $closingAt): string
    {
        if ($paidAt instanceof DateTimeInterface) {
            return CreditCardStatement::STATUS_PAID;
        }

        return today()->gt($closingAt)
            ? CreditCardStatement::STATUS_CLOSED
            : CreditCardStatement::STATUS_OPEN;
    }
}
