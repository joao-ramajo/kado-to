<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CreditCardStatement;
use App\Models\Expense;
use App\Models\Source;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoAccountSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $user = User::query()->updateOrCreate(['email' => 'demo@kado.local'], [
                'name' => 'Conta Demo Kado',
                'password' => Hash::make('Aa123123'),
            ]);

            // Mantem o seeder idempotente sem acumular dados antigos.
            Expense::query()->where('user_id', $user->id)->delete();
            CreditCardStatement::query()
                ->whereHas('source', fn ($query) => $query->where('user_id', $user->id))
                ->delete();
            Category::query()->where('user_id', $user->id)->delete();
            Source::query()->where('user_id', $user->id)->delete();

            $now = now();
            $sources = $this->createSources($user->id);
            $categories = $this->createCategories($user->id);

            $this->createCashExpenses($user->id, $sources, $categories);
            $this->createCardFlows($user->id, $sources, $categories, $now);
        });
    }

    /**
     * @return array<string, Source>
     */
    private function createSources(int $userId): array
    {
        return [
            'principal' => Source::query()->create([
                'user_id' => $userId,
                'name' => 'Carteira principal',
                'type' => Source::TYPE_CASH_LIKE,
                'color' => '#3B82F6',
                'is_default' => true,
                'allow_negative' => true,
            ]),
            'reserva' => Source::query()->create([
                'user_id' => $userId,
                'name' => 'Reserva de emergência',
                'type' => Source::TYPE_CASH_LIKE,
                'color' => '#10B981',
                'is_default' => false,
                'allow_negative' => false,
            ]),
            'viagem' => Source::query()->create([
                'user_id' => $userId,
                'name' => 'Fundo viagem',
                'type' => Source::TYPE_CASH_LIKE,
                'color' => '#8B5CF6',
                'is_default' => false,
                'allow_negative' => true,
            ]),
            'cartao_principal' => Source::query()->create([
                'user_id' => $userId,
                'name' => 'Cartão Demo',
                'type' => Source::TYPE_CREDIT_CARD,
                'color' => '#EF4444',
                'is_default' => false,
                'allow_negative' => false,
                'credit_limit' => 320000,
                'statement_closing_day' => 8,
                'statement_due_day' => 15,
            ]),
            'cartao_viagem' => Source::query()->create([
                'user_id' => $userId,
                'name' => 'Cartão Viagem',
                'type' => Source::TYPE_CREDIT_CARD,
                'color' => '#F59E0B',
                'is_default' => false,
                'allow_negative' => false,
                'credit_limit' => 450000,
                'statement_closing_day' => 20,
                'statement_due_day' => 28,
            ]),
        ];
    }

    /**
     * @return array<string, Category>
     */
    private function createCategories(int $userId): array
    {
        return [
            'moradia' => Category::query()->create([
                'name' => 'Moradia',
                'color' => '#EF4444',
                'user_id' => $userId,
            ]),
            'alimentacao' => Category::query()->create([
                'name' => 'Alimentação',
                'color' => '#F59E0B',
                'user_id' => $userId,
            ]),
            'transporte' => Category::query()->create([
                'name' => 'Transporte',
                'color' => '#06B6D4',
                'user_id' => $userId,
            ]),
            'lazer' => Category::query()->create([
                'name' => 'Lazer',
                'color' => '#A855F7',
                'user_id' => $userId,
            ]),
            'saude' => Category::query()->create([
                'name' => 'Saúde',
                'color' => '#14B8A6',
                'user_id' => $userId,
            ]),
            'educacao' => Category::query()->create([
                'name' => 'Educação',
                'color' => '#2563EB',
                'user_id' => $userId,
            ]),
            'viagem_categoria' => Category::query()->create([
                'name' => 'Viagem',
                'color' => '#EC4899',
                'user_id' => $userId,
            ]),
        ];
    }

    /** @param array<string, Source> $sources @param array<string, Category> $categories */
    private function createCashExpenses(int $userId, array $sources, array $categories): void
    {
        $expenses = [
            [
                'title' => 'Salário mensal',
                'amount' => 850000,
                'type' => 'income',
                'status' => 'paid',
                'source_id' => $sources['principal']->id,
                'payment_date' => '2026-01-05 09:00:00',
                'created_at' => '2026-01-05 09:00:00',
                'category_id' => null,
            ],
            [
                'title' => 'Aluguel apartamento',
                'amount' => 220000,
                'type' => 'expense',
                'status' => 'paid',
                'source_id' => $sources['principal']->id,
                'payment_date' => '2026-01-06 10:00:00',
                'created_at' => '2026-01-06 10:00:00',
                'category_id' => $categories['moradia']->id,
            ],
            [
                'title' => 'Supermercado mensal',
                'amount' => 68000,
                'type' => 'expense',
                'status' => 'paid',
                'source_id' => $sources['principal']->id,
                'payment_date' => '2026-01-09 19:30:00',
                'created_at' => '2026-01-09 19:30:00',
                'category_id' => $categories['alimentacao']->id,
            ],
            [
                'title' => 'Consulta médica',
                'amount' => 35000,
                'type' => 'expense',
                'status' => 'pending',
                'source_id' => $sources['principal']->id,
                'payment_date' => null,
                'created_at' => '2026-01-12 08:30:00',
                'category_id' => $categories['saude']->id,
            ],
            [
                'title' => 'Uber trabalho',
                'amount' => 2800,
                'type' => 'expense',
                'status' => 'paid',
                'source_id' => $sources['principal']->id,
                'payment_date' => '2026-01-14 18:45:00',
                'created_at' => '2026-01-14 18:45:00',
                'category_id' => $categories['transporte']->id,
            ],
            [
                'title' => 'Transferência para reserva',
                'amount' => 100000,
                'type' => 'income',
                'status' => 'paid',
                'source_id' => $sources['reserva']->id,
                'payment_date' => '2026-01-20 11:00:00',
                'created_at' => '2026-01-20 11:00:00',
                'category_id' => null,
            ],
            [
                'title' => 'Jantar especial',
                'amount' => 18500,
                'type' => 'expense',
                'status' => 'paid',
                'source_id' => $sources['principal']->id,
                'payment_date' => '2026-02-02 21:15:00',
                'created_at' => '2026-02-02 21:15:00',
                'category_id' => $categories['lazer']->id,
            ],
            [
                'title' => 'Salário mensal',
                'amount' => 850000,
                'type' => 'income',
                'status' => 'paid',
                'source_id' => $sources['principal']->id,
                'payment_date' => '2026-02-05 09:00:00',
                'created_at' => '2026-02-05 09:00:00',
                'category_id' => null,
            ],
            [
                'title' => 'Parcela do curso',
                'amount' => 62000,
                'type' => 'expense',
                'status' => 'pending',
                'source_id' => $sources['principal']->id,
                'payment_date' => null,
                'created_at' => '2026-02-08 10:00:00',
                'category_id' => $categories['educacao']->id,
            ],
            [
                'title' => 'Passagem aérea promoção',
                'amount' => 140000,
                'type' => 'expense',
                'status' => 'paid',
                'source_id' => $sources['viagem']->id,
                'payment_date' => '2026-02-10 14:00:00',
                'created_at' => '2026-02-10 14:00:00',
                'category_id' => $categories['viagem_categoria']->id,
            ],
            [
                'title' => 'Hotel viagem',
                'amount' => 98000,
                'type' => 'expense',
                'status' => 'overdue',
                'source_id' => $sources['viagem']->id,
                'payment_date' => null,
                'created_at' => '2026-02-12 16:20:00',
                'category_id' => $categories['viagem_categoria']->id,
            ],
            [
                'title' => 'Freela website',
                'amount' => 210000,
                'type' => 'income',
                'status' => 'pending',
                'source_id' => $sources['principal']->id,
                'payment_date' => null,
                'created_at' => '2026-02-18 12:10:00',
                'category_id' => null,
            ],
            [
                'title' => 'Farmácia',
                'amount' => 12400,
                'type' => 'expense',
                'status' => 'paid',
                'source_id' => $sources['principal']->id,
                'payment_date' => '2026-03-01 18:00:00',
                'created_at' => '2026-03-01 18:00:00',
                'category_id' => $categories['saude']->id,
            ],
            [
                'title' => 'Combustível',
                'amount' => 24500,
                'type' => 'expense',
                'status' => 'pending',
                'source_id' => $sources['principal']->id,
                'payment_date' => null,
                'created_at' => '2026-03-03 07:45:00',
                'category_id' => $categories['transporte']->id,
            ],
            [
                'title' => 'Aporte reserva',
                'amount' => 75000,
                'type' => 'income',
                'status' => 'paid',
                'source_id' => $sources['reserva']->id,
                'payment_date' => '2026-03-05 09:30:00',
                'created_at' => '2026-03-05 09:30:00',
                'category_id' => null,
            ],
        ];

        foreach ($expenses as $item) {
            Expense::query()->create([
                'title' => $item['title'],
                'amount' => $item['amount'],
                'user_id' => $userId,
                'status' => $item['status'],
                'type' => $item['type'],
                'payment_date' => $item['payment_date'],
                'purchase_date' => null,
                'due_date' => null,
                'category_id' => $item['category_id'],
                'source_id' => $item['source_id'],
                'origin_type' => Expense::ORIGIN_DIRECT,
                'occurrence_type' => Expense::OCCURRENCE_DIRECT,
                'credit_card_statement_id' => null,
                'installment_group_id' => null,
                'installment_number' => null,
                'installment_total' => null,
                'created_at' => $item['created_at'],
                'updated_at' => $item['created_at'],
            ]);
        }
    }

    /** @param array<string, Source> $sources @param array<string, Category> $categories */
    private function createCardFlows(int $userId, array $sources, array $categories, DateTimeInterface $now): void
    {
        $currentMonth = $now->format('Y-m-01');
        $previousMonth = $now->modify('-1 month')->format('Y-m-01');

        $openStatement = CreditCardStatement::query()->create([
            'source_id' => $sources['cartao_principal']->id,
            'reference_month' => $currentMonth,
            'closing_at' => date('Y-m-d', strtotime($currentMonth.' +7 days')),
            'due_at' => date('Y-m-d', strtotime($currentMonth.' +14 days')),
            'status' => CreditCardStatement::STATUS_OPEN,
            'total_amount' => 0,
            'paid_at' => null,
            'payment_source_id' => null,
        ]);

        $closedStatement = CreditCardStatement::query()->create([
            'source_id' => $sources['cartao_principal']->id,
            'reference_month' => $previousMonth,
            'closing_at' => date('Y-m-d', strtotime($previousMonth.' +7 days')),
            'due_at' => date('Y-m-d', strtotime($previousMonth.' +14 days')),
            'status' => CreditCardStatement::STATUS_CLOSED,
            'total_amount' => 0,
            'paid_at' => null,
            'payment_source_id' => null,
        ]);

        $paidAt = date('Y-m-d H:i:s', strtotime($previousMonth.' +20 days'));
        $paidStatement = CreditCardStatement::query()->create([
            'source_id' => $sources['cartao_viagem']->id,
            'reference_month' => $previousMonth,
            'closing_at' => date('Y-m-d', strtotime($previousMonth.' +19 days')),
            'due_at' => date('Y-m-d', strtotime($previousMonth.' +27 days')),
            'status' => CreditCardStatement::STATUS_PAID,
            'total_amount' => 0,
            'paid_at' => $paidAt,
            'payment_source_id' => $sources['principal']->id,
        ]);

        $this->createCardExpense([
            'title' => 'Notebook parcelado - 1/3',
            'amount' => 45000,
            'user_id' => $userId,
            'status' => 'pending',
            'payment_date' => null,
            'purchase_date' => date('Y-m-d', strtotime($currentMonth.' +1 day')),
            'due_date' => $openStatement->due_at->toDateString(),
            'category_id' => $categories['educacao']->id,
            'source_id' => $sources['cartao_principal']->id,
            'statement_id' => $openStatement->id,
            'installment_group_id' => 'demo-notebook-001',
            'installment_number' => 1,
            'installment_total' => 3,
        ]);
        $this->createCardExpense([
            'title' => 'Notebook parcelado - 2/3',
            'amount' => 45000,
            'user_id' => $userId,
            'status' => 'pending',
            'payment_date' => null,
            'purchase_date' => date('Y-m-d', strtotime($currentMonth.' +2 day')),
            'due_date' => $openStatement->due_at->toDateString(),
            'category_id' => $categories['educacao']->id,
            'source_id' => $sources['cartao_principal']->id,
            'statement_id' => $openStatement->id,
            'installment_group_id' => 'demo-notebook-001',
            'installment_number' => 2,
            'installment_total' => 3,
        ]);
        $this->createCardExpense([
            'title' => 'Fone bluetooth',
            'amount' => 18000,
            'user_id' => $userId,
            'status' => 'pending',
            'payment_date' => null,
            'purchase_date' => date('Y-m-d', strtotime($currentMonth.' +4 day')),
            'due_date' => $openStatement->due_at->toDateString(),
            'category_id' => $categories['lazer']->id,
            'source_id' => $sources['cartao_principal']->id,
            'statement_id' => $openStatement->id,
            'installment_group_id' => null,
            'installment_number' => null,
            'installment_total' => null,
        ]);
        $this->createCardExpense([
            'title' => 'Curso online',
            'amount' => 62000,
            'user_id' => $userId,
            'status' => 'pending',
            'payment_date' => null,
            'purchase_date' => date('Y-m-d', strtotime($previousMonth.' +3 day')),
            'due_date' => $closedStatement->due_at->toDateString(),
            'category_id' => $categories['educacao']->id,
            'source_id' => $sources['cartao_principal']->id,
            'statement_id' => $closedStatement->id,
            'installment_group_id' => null,
            'installment_number' => null,
            'installment_total' => null,
        ]);
        $this->createCardExpense([
            'title' => 'Passagem internacional',
            'amount' => 98000,
            'user_id' => $userId,
            'status' => 'paid',
            'payment_date' => $paidAt,
            'purchase_date' => date('Y-m-d', strtotime($previousMonth.' +5 day')),
            'due_date' => $paidStatement->due_at->toDateString(),
            'category_id' => $categories['viagem_categoria']->id,
            'source_id' => $sources['cartao_viagem']->id,
            'statement_id' => $paidStatement->id,
            'installment_group_id' => null,
            'installment_number' => null,
            'installment_total' => null,
        ]);
        $this->createCardExpense([
            'title' => 'Hospedagem',
            'amount' => 54000,
            'user_id' => $userId,
            'status' => 'paid',
            'payment_date' => $paidAt,
            'purchase_date' => date('Y-m-d', strtotime($previousMonth.' +8 day')),
            'due_date' => $paidStatement->due_at->toDateString(),
            'category_id' => $categories['viagem_categoria']->id,
            'source_id' => $sources['cartao_viagem']->id,
            'statement_id' => $paidStatement->id,
            'installment_group_id' => null,
            'installment_number' => null,
            'installment_total' => null,
        ]);

        Expense::query()->create([
            'title' => 'Pagamento de fatura - Cartão Viagem - 02/2026',
            'amount' => 152000,
            'user_id' => $userId,
            'status' => 'paid',
            'type' => 'expense',
            'payment_date' => $paidAt,
            'purchase_date' => $paidStatement->reference_month->toDateString(),
            'due_date' => $paidStatement->due_at->toDateString(),
            'category_id' => null,
            'source_id' => $sources['principal']->id,
            'origin_type' => Expense::ORIGIN_DIRECT,
            'occurrence_type' => Expense::OCCURRENCE_INVOICE_PAYMENT,
            'credit_card_statement_id' => $paidStatement->id,
            'installment_group_id' => null,
            'installment_number' => null,
            'installment_total' => null,
            'created_at' => $paidAt,
            'updated_at' => $paidAt,
        ]);

        $this->syncStatementTotals([$openStatement->id, $closedStatement->id, $paidStatement->id]);
    }

    /** @param array<string, int|string|null> $attributes */
    private function createCardExpense(array $attributes): void
    {
        Expense::query()->create([
            'title' => $attributes['title'],
            'amount' => $attributes['amount'],
            'user_id' => $attributes['user_id'],
            'status' => $attributes['status'],
            'type' => 'expense',
            'payment_date' => $attributes['payment_date'],
            'purchase_date' => $attributes['purchase_date'],
            'due_date' => $attributes['due_date'],
            'category_id' => $attributes['category_id'],
            'source_id' => $attributes['source_id'],
            'origin_type' => Expense::ORIGIN_CREDIT_CARD,
            'occurrence_type' => Expense::OCCURRENCE_PURCHASE,
            'credit_card_statement_id' => $attributes['statement_id'],
            'installment_group_id' => $attributes['installment_group_id'],
            'installment_number' => $attributes['installment_number'],
            'installment_total' => $attributes['installment_total'],
            'created_at' => $attributes['payment_date'] ?? $attributes['purchase_date'],
            'updated_at' => $attributes['payment_date'] ?? $attributes['purchase_date'],
        ]);
    }

    /** @param list<int> $statementIds */
    private function syncStatementTotals(array $statementIds): void
    {
        foreach ($statementIds as $statementId) {
            $statement = CreditCardStatement::query()->findOrFail($statementId);
            $totalAmount = (int) Expense::query()
                ->where('credit_card_statement_id', $statementId)
                ->where('occurrence_type', Expense::OCCURRENCE_PURCHASE)
                ->sum('amount');

            $statement->update([
                'total_amount' => $totalAmount,
            ]);
        }
    }
}
