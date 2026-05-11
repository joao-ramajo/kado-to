<?php

declare(strict_types=1);

use App\Action\Xlsx\ExpensesListSheet;
use App\Action\Xlsx\GenerateExpensesXlsxAction;
use App\Action\Xlsx\SourcesSummarySheet;
use App\DTO\Xlsx\GenerateExpensesXlsxInput;
use App\Models\Expense;
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

    expect($content)->toContain('TITLE;AMOUNT;STATUS;TYPE;PAYMENT_DATE;DUE_DATE;CREATED_AT;CATEGORY_NAME;SOURCE_NAME')
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

    expect($spreadsheet->getSheetByName('Despesas')?->getCell('A1')->getValue())->toBe('Descrição')
        ->and($spreadsheet->getSheetByName('Resumo por Fonte')?->getCell('A3')->getValue())->toBe('TOTAL');
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
        $logger,
    ))->execute(new GenerateExpensesXlsxInput($user->id))->response;

    expect($response->headers->get('Content-Disposition'))->toContain('despesas.xlsx');

    $csvResponse = resolve(ExportService::class)->execute(Request::create('/fake', 'GET', ['type' => 'csv']));
    $xlsxResponse = resolve(ExportService::class)->execute(Request::create('/fake', 'GET', ['type' => 'xlsx']));

    expect($csvResponse->headers->get('Content-Type'))->toContain('text/csv')
        ->and($xlsxResponse->headers->get('Content-Type'))->toContain('spreadsheetml');
});
