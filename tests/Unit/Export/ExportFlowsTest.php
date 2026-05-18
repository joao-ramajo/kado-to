<?php

declare(strict_types=1);

use App\Action\Xlsx\ExpensesListSheet;
use App\Action\Xlsx\GenerateExpensesXlsxAction;
use App\Action\Xlsx\InsightsSheet;
use App\Action\Xlsx\SourcesSummarySheet;
use App\DTO\Xlsx\GenerateExpensesXlsxInput;
use App\Models\CreditCardStatement;
use App\Models\Expense;
use App\Models\Source;
use App\Models\User;
use App\Services\ExportService;
use App\Strategy\CsvExportStrategy;
use App\Strategy\XlsxExportStrategy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Psr\Log\LoggerInterface;

test('csv export gera cabeçalho e fonte da despesa', function (): void {
    $user = User::factory()->create(['name' => 'John Doe']);
    $source = $user->sources()->where('is_default', true)->firstOrFail();
    Auth::login($user);

    Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $source->id,
        'title' => 'Mercado',
        'amount' => 15000,
        'status' => 'paid',
        'type' => 'expense',
    ]);

    $response = resolve(CsvExportStrategy::class)->execute();

    ob_start();
    $response->sendContent();
    $content = (string) ob_get_clean();

    expect($content)->toContain('TITLE;AMOUNT;STATUS;TYPE;PAYMENT_DATE;DUE_DATE;CREATED_AT;CATEGORY_NAME;SOURCE_NAME;SOURCE_TYPE')
        ->and($content)->toContain('Mercado')
        ->and($content)->toContain($source->name);
});

test('xlsx export usa sources no schema atual e expõe coluna de fonte', function (): void {
    $user = User::factory()->create(['name' => 'John Doe']);
    $source = $user->sources()->where('is_default', true)->firstOrFail();
    Auth::login($user);

    Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $source->id,
        'title' => 'Mercado',
        'amount' => 15000,
        'status' => 'paid',
        'type' => 'expense',
    ]);

    $response = resolve(XlsxExportStrategy::class)->execute();

    ob_start();
    $response->sendContent();
    $content = (string) ob_get_clean();

    $path = tempnam(sys_get_temp_dir(), 'xlsx-test');
    file_put_contents($path, $content);

    $sheet = (new XlsxReader)->load($path)->getActiveSheet();

    expect($sheet->getCell('A1')->getValue())->toBe('TÍTULO')
        ->and($sheet->getCell('I2')->getValue())->toBe($source->name);

    @unlink($path);
});

test('planilhas xlsx agregam abas e totais relevantes', function (): void {
    $user = User::factory()->create();
    $source = $user->sources()->where('is_default', true)->firstOrFail();
    Auth::login($user);

    Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $source->id,
        'title' => 'Salário',
        'amount' => 500000,
        'type' => 'income',
        'status' => 'paid',
        'payment_date' => '2026-05-01 10:00:00',
    ]);

    $spreadsheet = new Spreadsheet;
    (new ExpensesListSheet)->addTo($spreadsheet);
    (new SourcesSummarySheet)->addTo($spreadsheet);
    (new InsightsSheet)->addTo($spreadsheet);

    expect($spreadsheet->getSheetByName('Despesas')?->getCell('A1')->getValue())->toBe('Descrição')
        ->and($spreadsheet->getSheetByName('Resumo Financeiro')?->getCell('A1')->getValue())->toBe('Totais por Fonte')
        ->and($spreadsheet->getSheetByName('Resumo Financeiro')?->getCell('A5')->getValue())->toBe('Cartões e Faturas')
        ->and($spreadsheet->getSheetByName('Insights')?->getCell('A1')->getValue())->toBe('Insights');
});

test('listagem xlsx expõe contexto de cartão, parcela e fatura', function (): void {
    $user = User::factory()->create();
    $cashSource = $user->sources()->where('is_default', true)->firstOrFail();
    $creditCard = Source::factory()->creditCard()->create([
        'user_id' => $user->id,
        'name' => 'Cartão Azul',
        'statement_closing_day' => 5,
        'statement_due_day' => 10,
    ]);
    Auth::login($user);

    $statement = CreditCardStatement::factory()->create([
        'source_id' => $creditCard->id,
        'reference_month' => '2026-04-01',
        'closing_at' => '2026-04-05',
        'due_at' => '2026-04-10',
        'status' => CreditCardStatement::STATUS_PAID,
        'paid_at' => '2026-04-10 12:00:00',
        'payment_source_id' => $cashSource->id,
    ]);

    Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $creditCard->id,
        'title' => 'Notebook',
        'amount' => 50000,
        'status' => 'paid',
        'type' => 'expense',
        'origin_type' => Expense::ORIGIN_CREDIT_CARD,
        'occurrence_type' => Expense::OCCURRENCE_PURCHASE,
        'purchase_date' => '2026-03-28',
        'payment_date' => '2026-04-10 12:00:00',
        'due_date' => '2026-04-10',
        'credit_card_statement_id' => $statement->id,
        'installment_group_id' => 'grupo-1',
        'installment_number' => 1,
        'installment_total' => 3,
        'created_at' => '2026-04-01 10:00:00',
        'updated_at' => '2026-04-01 10:00:00',
    ]);

    Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $cashSource->id,
        'title' => 'Pagamento de fatura - Cartão Azul - 04/2026',
        'amount' => 50000,
        'status' => 'paid',
        'type' => 'expense',
        'origin_type' => Expense::ORIGIN_DIRECT,
        'occurrence_type' => Expense::OCCURRENCE_INVOICE_PAYMENT,
        'purchase_date' => '2026-04-01',
        'payment_date' => '2026-04-10 12:00:00',
        'due_date' => '2026-04-10',
        'category_id' => null,
        'credit_card_statement_id' => $statement->id,
        'created_at' => '2026-04-10 12:00:00',
        'updated_at' => '2026-04-10 12:00:00',
    ]);

    $spreadsheet = new Spreadsheet;
    (new ExpensesListSheet)->addTo($spreadsheet);
    $sheet = $spreadsheet->getSheetByName('Despesas');

    expect($sheet?->getCell('E1')->getValue())->toBe('Data da Compra')
        ->and($sheet?->getCell('F1')->getValue())->toBe('Movimento')
        ->and($sheet?->getCell('F2')->getValue())->toBe('Pagamento de fatura')
        ->and($sheet?->getCell('H2')->getValue())->toBe('Cartão Azul - 04/2026')
        ->and($sheet?->getCell('D2')->getValue())->toBeNull()
        ->and($sheet?->getCell('H3')->getValue())->toBe('Cartão Azul - 04/2026')
        ->and($sheet?->getCell('I3')->getValue())->toBe('1/3');
});

test('resumo financeiro xlsx resume ganhos e gastos por fonte sem inflar cartão', function (): void {
    $user = User::factory()->create();
    $cashSource = $user->sources()->where('is_default', true)->firstOrFail();
    $creditCard = Source::factory()->creditCard()->create([
        'user_id' => $user->id,
        'name' => 'Cartão Azul',
    ]);
    Auth::login($user);

    Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $cashSource->id,
        'title' => 'Salário',
        'amount' => 300000,
        'type' => 'income',
        'status' => 'paid',
    ]);

    Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $cashSource->id,
        'title' => 'Mercado',
        'amount' => 50000,
        'type' => 'expense',
        'status' => 'paid',
        'occurrence_type' => Expense::OCCURRENCE_DIRECT,
    ]);

    $openStatement = CreditCardStatement::factory()->create([
        'source_id' => $creditCard->id,
        'reference_month' => '2026-05-01',
        'closing_at' => '2026-05-05',
        'due_at' => '2026-05-10',
        'status' => CreditCardStatement::STATUS_OPEN,
        'total_amount' => 70000,
        'payment_source_id' => null,
        'paid_at' => null,
    ]);

    Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $creditCard->id,
        'title' => 'Notebook',
        'amount' => 70000,
        'type' => 'expense',
        'status' => 'pending',
        'origin_type' => Expense::ORIGIN_CREDIT_CARD,
        'occurrence_type' => Expense::OCCURRENCE_PURCHASE,
        'credit_card_statement_id' => $openStatement->id,
    ]);

    $paidStatement = CreditCardStatement::factory()->create([
        'source_id' => $creditCard->id,
        'reference_month' => '2026-04-01',
        'closing_at' => '2026-04-05',
        'due_at' => '2026-04-10',
        'status' => CreditCardStatement::STATUS_PAID,
        'total_amount' => 40000,
        'payment_source_id' => $cashSource->id,
        'paid_at' => '2026-04-10 12:00:00',
    ]);

    Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $cashSource->id,
        'title' => 'Pagamento de fatura',
        'amount' => 40000,
        'type' => 'expense',
        'status' => 'paid',
        'origin_type' => Expense::ORIGIN_DIRECT,
        'occurrence_type' => Expense::OCCURRENCE_INVOICE_PAYMENT,
        'credit_card_statement_id' => $paidStatement->id,
    ]);

    $spreadsheet = new Spreadsheet;
    (new SourcesSummarySheet)->addTo($spreadsheet);
    $sheet = $spreadsheet->getSheetByName('Resumo Financeiro');

    expect($sheet?->getCell('A2')->getValue())->toBe('Fonte')
        ->and($sheet?->getCell('D2')->getValue())->toBe('Saldo final')
        ->and($sheet?->getCell('A3')->getValue())->toBe($cashSource->name)
        ->and($sheet?->getCell('B3')->getCalculatedValue())->toBe(3000.0)
        ->and($sheet?->getCell('C3')->getCalculatedValue())->toBe(900.0)
        ->and($sheet?->getCell('D3')->getCalculatedValue())->toBe(2100.0)
        ->and($sheet?->getCell('A4')->getValue())->toBe('Cartão Azul')
        ->and((float) $sheet?->getCell('B4')->getValue())->toBe(0.0)
        ->and($sheet?->getCell('C4')->getCalculatedValue())->toBe(700.0)
        ->and($sheet?->getCell('D4')->getCalculatedValue())->toBe(4300.0)
        ->and($sheet?->getCell('A8')->getValue())->toBe('Cartão Azul')
        ->and($sheet?->getCell('C8')->getValue())->toBe('Aberta')
        ->and($sheet?->getCell('A9')->getValue())->toBe('Cartão Azul')
        ->and($sheet?->getCell('C9')->getValue())->toBe('Paga');
});

test('insights xlsx expõe leituras híbridas sem duplicar consumo', function (): void {
    Illuminate\Support\Facades\Date::setTestNow('2026-05-20 10:00:00');

    try {
        $user = User::factory()->create();
        $cashSource = $user->sources()->where('is_default', true)->firstOrFail();
        $creditCard = Source::factory()->creditCard()->create([
            'user_id' => $user->id,
            'name' => 'Cartão Azul',
        ]);
        $categoryFood = \App\Models\Category::factory()->create([
            'user_id' => $user->id,
            'name' => 'Alimentação',
        ]);
        $categoryTech = \App\Models\Category::factory()->create([
            'user_id' => $user->id,
            'name' => 'Tecnologia',
        ]);
        Auth::login($user);

        Expense::factory()->create([
            'user_id' => $user->id,
            'source_id' => $cashSource->id,
            'title' => 'Mercado premium',
            'amount' => 120000,
            'type' => 'expense',
            'status' => 'paid',
            'category_id' => $categoryFood->id,
            'occurrence_type' => Expense::OCCURRENCE_DIRECT,
            'payment_date' => '2026-05-18 11:00:00',
            'created_at' => '2026-05-18 11:00:00',
        ]);
        Expense::factory()->create([
            'user_id' => $user->id,
            'source_id' => $cashSource->id,
            'title' => 'Almoço',
            'amount' => 3000,
            'type' => 'expense',
            'status' => 'paid',
            'category_id' => $categoryFood->id,
            'occurrence_type' => Expense::OCCURRENCE_DIRECT,
            'payment_date' => '2026-05-19 12:00:00',
            'created_at' => '2026-05-19 12:00:00',
        ]);
        Expense::factory()->create([
            'user_id' => $user->id,
            'source_id' => $creditCard->id,
            'title' => 'Notebook',
            'amount' => 90000,
            'type' => 'expense',
            'status' => 'pending',
            'category_id' => $categoryTech->id,
            'origin_type' => Expense::ORIGIN_CREDIT_CARD,
            'occurrence_type' => Expense::OCCURRENCE_PURCHASE,
            'purchase_date' => '2026-05-17',
            'created_at' => '2026-05-17 09:00:00',
        ]);
        Expense::factory()->create([
            'user_id' => $user->id,
            'source_id' => $creditCard->id,
            'title' => 'Mouse',
            'amount' => 10000,
            'type' => 'expense',
            'status' => 'pending',
            'category_id' => $categoryTech->id,
            'origin_type' => Expense::ORIGIN_CREDIT_CARD,
            'occurrence_type' => Expense::OCCURRENCE_PURCHASE,
            'purchase_date' => '2026-05-18',
            'created_at' => '2026-05-18 09:00:00',
        ]);
        Expense::factory()->create([
            'user_id' => $user->id,
            'source_id' => $cashSource->id,
            'title' => 'Pagamento de fatura',
            'amount' => 50000,
            'type' => 'expense',
            'status' => 'paid',
            'category_id' => null,
            'occurrence_type' => Expense::OCCURRENCE_INVOICE_PAYMENT,
            'payment_date' => '2026-05-19 09:00:00',
            'created_at' => '2026-05-19 09:00:00',
        ]);

        CreditCardStatement::factory()->create([
            'source_id' => $creditCard->id,
            'reference_month' => '2026-05-01',
            'status' => CreditCardStatement::STATUS_OPEN,
            'total_amount' => 100000,
        ]);

        $spreadsheet = new Spreadsheet;
        (new InsightsSheet)->addTo($spreadsheet);
        $sheet = $spreadsheet->getSheetByName('Insights');

        expect($sheet?->getCell('A3')->getValue())->toBe('Maior gasto pago recente')
            ->and($sheet?->getCell('B3')->getValue())->toBe('R$ 1.200,00')
            ->and((string) $sheet?->getCell('C3')->getValue())->toContain('Mercado premium')
            ->and($sheet?->getCell('A4')->getValue())->toBe('Categoria mais frequente')
            ->and($sheet?->getCell('B4')->getValue())->toBe('Alimentação')
            ->and($sheet?->getCell('A5')->getValue())->toBe('Categoria de maior valor')
            ->and($sheet?->getCell('B5')->getValue())->toBe('Alimentação')
            ->and((string) $sheet?->getCell('C5')->getValue())->toContain('R$ 1.230,00')
            ->and($sheet?->getCell('A6')->getValue())->toBe('Fonte mais usada')
            ->and($sheet?->getCell('B6')->getValue())->toBe('Cartão Azul')
            ->and($sheet?->getCell('A7')->getValue())->toBe('Cartão em aberto')
            ->and($sheet?->getCell('B7')->getValue())->toBe('R$ 1.000,00')
            ->and($sheet?->getCell('A8')->getValue())->toBe('Média diária de gasto pago')
            ->and($sheet?->getCell('B8')->getValue())->toBe('R$ 57,67');
    } finally {
        Illuminate\Support\Facades\Date::setTestNow();
    }
});

test('generate expenses xlsx action e export service retornam respostas esperadas', function (): void {
    $user = User::factory()->create();
    $sourceId = $user->sources()->where('is_default', true)->value('id');
    Auth::login($user);

    Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $sourceId,
        'title' => 'Mercado',
    ]);

    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('info')->twice();

    $response = (new GenerateExpensesXlsxAction(
        new ExpensesListSheet,
        new SourcesSummarySheet,
        new InsightsSheet,
        $logger,
    ))->execute(new GenerateExpensesXlsxInput($user->id))->response;

    expect($response->headers->get('Content-Disposition'))->toContain('despesas.xlsx');

    $csvResponse = resolve(ExportService::class)->execute(Request::create('/fake', 'GET', ['type' => 'csv']));
    $xlsxResponse = resolve(ExportService::class)->execute(Request::create('/fake', 'GET', ['type' => 'xlsx']));

    expect($csvResponse->headers->get('Content-Type'))->toContain('text/csv')
        ->and($xlsxResponse->headers->get('Content-Type'))->toContain('spreadsheetml');
});
