<?php

declare(strict_types=1);

use App\Action\Dashboard\GetExpensesAction;
use App\Action\Dashboard\GetSummaryAction;
use App\Action\ImportCsvData;
use App\DTO\Dashboard\GetExpensesInput;
use App\DTO\Dashboard\GetSummaryInput;
use App\Models\Category;
use App\Models\CreditCardStatement;
use App\Models\Expense;
use App\Models\Source;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Psr\Log\LoggerInterface;

test('get expenses filtra por query, categoria e mês útil do fluxo', function (): void {
    $user = User::factory()->create();
    $defaultSourceId = $user->sources()->where('is_default', true)->value('id');
    $category = Category::factory()->create(['user_id' => $user->id, 'name' => 'Transporte']);

    $expectedExpense = Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $defaultSourceId,
        'category_id' => $category->id,
        'title' => 'Uber - Centro',
        'type' => 'expense',
        'status' => 'paid',
        'payment_date' => '2026-05-02 10:00:00',
        'created_at' => '2026-05-01 10:00:00',
        'updated_at' => '2026-05-01 10:00:00',
    ]);

    Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $defaultSourceId,
        'title' => 'Mercado',
        'status' => 'pending',
    ]);

    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('info')->twice();

    $expenses = (new GetExpensesAction($logger))->execute(new GetExpensesInput(
        userId: $user->id,
        status: 'all',
        query: 'uber',
        categoryId: $category->id,
        month: 5,
    ))->toArray();

    expect($expenses)->toHaveCount(1)
        ->and($expenses[0]['id'])->toBe($expectedExpense->id);
});

test('get summary calcula caixa e cartão sem misturar contextos', function (): void {
    $user = User::factory()->create();
    $defaultSourceId = $user->sources()->where('is_default', true)->value('id');
    $creditCard = Source::factory()->creditCard()->create(['user_id' => $user->id]);

    Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $defaultSourceId,
        'title' => 'Salário',
        'type' => 'income',
        'status' => 'paid',
        'amount' => 500000,
    ]);
    Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $defaultSourceId,
        'title' => 'Aluguel',
        'type' => 'expense',
        'status' => 'pending',
        'amount' => 120000,
    ]);

    $statement = CreditCardStatement::factory()->create([
        'source_id' => $creditCard->id,
        'status' => CreditCardStatement::STATUS_OPEN,
        'total_amount' => 80000,
    ]);
    Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $creditCard->id,
        'amount' => 80000,
        'status' => 'pending',
        'origin_type' => Expense::ORIGIN_CREDIT_CARD,
        'occurrence_type' => Expense::OCCURRENCE_PURCHASE,
        'credit_card_statement_id' => $statement->id,
    ]);

    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('info')->twice();

    $summary = (new GetSummaryAction($logger))->execute(new GetSummaryInput(
        userId: $user->id,
        defaultSourceId: $defaultSourceId,
    ));

    expect($summary->totalReceive)->toBe(500000)
        ->and($summary->totalExpense)->toBe(0)
        ->and($summary->expectedTotal)->toBe(380000)
        ->and($summary->creditCardOpenTotal)->toBe(80000)
        ->and($summary->creditCardLimitUsed)->toBe(80000);
});

test('import csv cria categoria e respeita alias da fonte padrão', function (): void {
    $user = User::factory()->create();
    Auth::login($user);

    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('info')->twice();

    $csv = <<<'CSV'
TITLE;AMOUNT;STATUS;TYPE;PAYMENT_DATE;DUE_DATE;CREATED_AT;CATEGORY_NAME;SOURCE_NAME
Mercado;15000;paid;expense;2026-02-01 10:00:00;2026-02-05;2026-02-01 10:00:00;Casa;principal
Mercado;15000;paid;expense;2026-02-01 10:00:00;2026-02-05;2026-02-01 10:00:00;Casa;principal
CSV;

    $file = UploadedFile::fake()->createWithContent('import.csv', $csv);

    $result = (new ImportCsvData($logger))->execute($file);

    $defaultSourceId = $user->sources()->where('is_default', true)->value('id');

    expect($result)->toBeTrue()
        ->and(Expense::query()->count())->toBe(2)
        ->and(Expense::query()->firstOrFail()->source_id)->toBe($defaultSourceId);

    $this->assertDatabaseHas('categories', [
        'name' => 'Casa',
        'user_id' => $user->id,
    ]);
});

test('import csv falha para arquivo inválido e cabeçalho inválido', function (): void {
    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldIgnoreMissing();

    $invalidFile = UploadedFile::fake()->create('import.pdf', 20, 'application/pdf');
    expect((new ImportCsvData($logger))->execute($invalidFile))->toBeFalse();

    $user = User::factory()->create();
    Auth::login($user);

    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldIgnoreMissing();

    $badCsv = UploadedFile::fake()->createWithContent('import.csv', "TITLE;AMOUNT\nMercado;1000");
    expect((new ImportCsvData($logger))->execute($badCsv))->toBeFalse();
});
