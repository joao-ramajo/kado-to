<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CreditCardStatement;
use App\Models\Source;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditCardStatement>
 */
class CreditCardStatementFactory extends Factory
{
    protected $model = CreditCardStatement::class;

    public function definition(): array
    {
        return [
            'source_id' => Source::factory(),
            'reference_month' => now()->startOfMonth(),
            'closing_at' => now()->startOfMonth()->day(5),
            'due_at' => now()->startOfMonth()->day(10),
            'status' => CreditCardStatement::STATUS_OPEN,
            'total_amount' => 0,
            'paid_at' => null,
            'payment_source_id' => null,
        ];
    }
}
