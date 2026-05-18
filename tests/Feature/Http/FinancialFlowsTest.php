<?php

declare(strict_types=1);

use App\Models\CreditCardStatement;
use App\Models\Expense;
use App\Models\Source;
use App\Models\User;
use Illuminate\Http\UploadedFile;

function authTokenFor(User $user): string
{
    return $user->createToken('test')->plainTextToken;
}

test('deve criar despesa na fonte padrao e refletir no resumo geral e nos detalhes da fonte', function (): void {
    $user = User::factory()->create();
    $token = authTokenFor($user);
    $defaultSource = $user->sources()->where('is_default', true)->firstOrFail();

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson(route('api.expenses.create'), [
            'title' => 'Aluguel',
            'amount' => 120000,
            'type' => 'expense',
            'status' => 'paid',
            'source_id' => $defaultSource->id,
        ]);

    $response->assertStatus(201);

    $summary = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson(route('api.get-summary'));

    $summary->assertStatus(200)
        ->assertJson([
            'total_receive' => 0,
            'total_expense' => 120000,
            'expected_total' => -120000,
        ]);

    $sourceDetails = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson(route('api.sources.details'));

    $source = collect($sourceDetails->json())->firstWhere('id', $defaultSource->id);

    expect($source)->not->toBeNull();
    expect($source['total_income'])->toBe(0);
    expect($source['total_expense'])->toBe(120000);
    expect($source['balance'])->toBe(-120000);
    expect($source['expenses_count'])->toBe(1);
});

test('deve criar nova fonte e operacoes nela nao devem afetar resumo geral da fonte principal', function (): void {
    $user = User::factory()->create();
    $token = authTokenFor($user);

    $createSource = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson(route('api.sources.create'), [
            'name' => 'Carteira Viagem',
            'color' => '#22aa99',
            'allow_negative' => true,
        ]);

    $createSource->assertStatus(201);

    $newSourceId = $createSource->json('data.id');

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson(route('api.expenses.create'), [
            'title' => 'Hotel',
            'amount' => 50000,
            'type' => 'expense',
            'status' => 'paid',
            'source_id' => $newSourceId,
        ])
        ->assertStatus(201);

    $summary = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson(route('api.get-summary'));

    $summary->assertStatus(200)
        ->assertJson([
            'total_receive' => 0,
            'total_expense' => 0,
            'expected_total' => 0,
        ]);

    $sourceDetails = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson(route('api.sources.details'));

    $createdSource = collect($sourceDetails->json())->firstWhere('id', $newSourceId);

    expect($createdSource)->not->toBeNull();
    expect($createdSource['total_expense'])->toBe(50000);
    expect($createdSource['balance'])->toBe(-50000);
});

test('deve atualizar uma fonte de caixa secundaria com sucesso', function (): void {
    $user = User::factory()->create();
    $token = authTokenFor($user);
    $source = Source::factory()->create([
        'user_id' => $user->id,
        'name' => 'Carteira antiga',
        'color' => '#111111',
        'allow_negative' => false,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->putJson(route('api.sources.update', ['id' => $source->id]), [
            'name' => 'Carteira atualizada',
            'color' => '#22aa99',
            'allow_negative' => true,
        ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Fonte atualizada com sucesso')
        ->assertJsonPath('data.name', 'Carteira atualizada')
        ->assertJsonPath('data.color', '#22aa99')
        ->assertJsonPath('data.allow_negative', true);

    $this->assertDatabaseHas('sources', [
        'id' => $source->id,
        'user_id' => $user->id,
        'name' => 'Carteira atualizada',
        'color' => '#22aa99',
        'allow_negative' => true,
    ]);
});

test('deve atualizar uma fonte de cartao com sucesso', function (): void {
    $user = User::factory()->create();
    $token = authTokenFor($user);
    $source = Source::factory()->creditCard()->create([
        'user_id' => $user->id,
        'name' => 'Visa antiga',
        'color' => '#111111',
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->putJson(route('api.sources.update', ['id' => $source->id]), [
            'name' => 'Visa atualizada',
            'color' => '#0055ff',
            'credit_limit' => 750000,
            'statement_closing_day' => 15,
            'statement_due_day' => 20,
        ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Fonte atualizada com sucesso')
        ->assertJsonPath('data.name', 'Visa atualizada')
        ->assertJsonPath('data.color', '#0055ff')
        ->assertJsonPath('data.credit_limit', 750000)
        ->assertJsonPath('data.statement_closing_day', 15)
        ->assertJsonPath('data.statement_due_day', 20)
        ->assertJsonPath('data.allow_negative', false);

    $this->assertDatabaseHas('sources', [
        'id' => $source->id,
        'user_id' => $user->id,
        'name' => 'Visa atualizada',
        'color' => '#0055ff',
        'credit_limit' => 750000,
        'statement_closing_day' => 15,
        'statement_due_day' => 20,
        'allow_negative' => false,
    ]);
});

test('deve bloquear edicao da fonte principal', function (): void {
    $user = User::factory()->create();
    $token = authTokenFor($user);
    $defaultSource = $user->sources()->where('is_default', true)->firstOrFail();

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->putJson(route('api.sources.update', ['id' => $defaultSource->id]), [
            'name' => 'Fonte principal editada',
            'color' => '#22aa99',
            'allow_negative' => true,
        ]);

    $response->assertStatus(400)
        ->assertJson([
            'message' => 'A fonte principal não pode ser editada.',
        ]);
});

test('deve excluir uma fonte de caixa secundaria e apagar suas despesas em cascata', function (): void {
    $user = User::factory()->create();
    $token = authTokenFor($user);
    $source = Source::factory()->create([
        'user_id' => $user->id,
        'name' => 'Carteira viagem',
        'color' => '#22aa99',
        'allow_negative' => true,
        'is_default' => false,
    ]);

    $expense = Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $source->id,
        'title' => 'Hotel',
        'amount' => 50000,
        'status' => 'paid',
        'type' => 'expense',
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->deleteJson(route('api.sources.delete', ['id' => $source->id]));

    $response->assertOk()
        ->assertJsonPath('message', 'Fonte excluída com sucesso.');

    $this->assertDatabaseMissing('sources', [
        'id' => $source->id,
    ]);

    $this->assertDatabaseMissing('expenses', [
        'id' => $expense->id,
    ]);
});

test('deve excluir uma fonte de cartao e apagar despesas e faturas em cascata', function (): void {
    $user = User::factory()->create();
    $token = authTokenFor($user);
    $source = Source::factory()->creditCard()->create([
        'user_id' => $user->id,
        'name' => 'Visa',
        'color' => '#0055ff',
        'credit_limit' => 750000,
        'statement_closing_day' => 15,
        'statement_due_day' => 20,
    ]);
    $statement = CreditCardStatement::factory()->create([
        'source_id' => $source->id,
        'reference_month' => now()->startOfMonth(),
        'closing_at' => now()->startOfMonth()->day(15),
        'due_at' => now()->startOfMonth()->day(20),
    ]);

    $purchase = Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $source->id,
        'title' => 'Notebook',
        'amount' => 125000,
        'status' => 'pending',
        'type' => 'expense',
        'origin_type' => Expense::ORIGIN_CREDIT_CARD,
        'occurrence_type' => Expense::OCCURRENCE_PURCHASE,
        'credit_card_statement_id' => $statement->id,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->deleteJson(route('api.sources.delete', ['id' => $source->id]));

    $response->assertOk()
        ->assertJsonPath('message', 'Fonte excluída com sucesso.');

    $this->assertDatabaseMissing('sources', [
        'id' => $source->id,
    ]);

    $this->assertDatabaseMissing('expenses', [
        'id' => $purchase->id,
    ]);

    $this->assertDatabaseMissing('credit_card_statements', [
        'id' => $statement->id,
    ]);
});

test('deve bloquear a exclusao da fonte principal', function (): void {
    $user = User::factory()->create();
    $token = authTokenFor($user);
    $defaultSource = $user->sources()->where('is_default', true)->firstOrFail();

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->deleteJson(route('api.sources.delete', ['id' => $defaultSource->id]));

    $response->assertStatus(400)
        ->assertJson([
            'message' => 'A fonte principal não pode ser excluída.',
        ]);
});

test('deve calcular expected_total considerando registros pendentes e pagos', function (): void {
    $user = User::factory()->create();
    $token = authTokenFor($user);
    $defaultSourceId = $user->sources()->where('is_default', true)->value('id');

    Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $defaultSourceId,
        'amount' => 20000,
        'type' => 'income',
        'status' => 'paid',
    ]);

    Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $defaultSourceId,
        'amount' => 5000,
        'type' => 'expense',
        'status' => 'pending',
    ]);

    Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $defaultSourceId,
        'amount' => 1000,
        'type' => 'income',
        'status' => 'pending',
    ]);

    $summary = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson(route('api.get-summary'));

    $summary->assertStatus(200)
        ->assertJson([
            'total_receive' => 20000,
            'total_expense' => 0,
            'expected_total' => 16000,
        ]);
});

test('deve marcar despesa como paga e manter expected_total consistente', function (): void {
    $user = User::factory()->create();
    $token = authTokenFor($user);
    $defaultSourceId = $user->sources()->where('is_default', true)->value('id');

    $expense = Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $defaultSourceId,
        'amount' => 7000,
        'type' => 'expense',
        'status' => 'pending',
        'payment_date' => null,
    ]);

    $before = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson(route('api.get-summary'));

    $before->assertJson([
        'total_expense' => 0,
        'expected_total' => -7000,
    ]);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson(route('api.expenses.mark-as-paid', ['id' => $expense->id]))
        ->assertStatus(200);

    $this->assertDatabaseHas('expenses', [
        'id' => $expense->id,
        'status' => 'paid',
    ]);

    expect(Expense::query()->findOrFail($expense->id)->payment_date)->not->toBeNull();

    $after = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson(route('api.get-summary'));

    $after->assertJson([
        'total_expense' => 7000,
        'expected_total' => -7000,
    ]);
});

test('deve importar csv e criar categoria automaticamente vinculando registros na fonte padrao', function (): void {
    $user = User::factory()->create();
    $token = authTokenFor($user);
    $defaultSourceId = $user->sources()->where('is_default', true)->value('id');

    $csv = <<<'CSV'
TITLE;AMOUNT;STATUS;TYPE;PAYMENT_DATE;DUE_DATE;CREATED_AT;CATEGORY_NAME;SOURCE_NAME
Mercado;15000;paid;expense;2026-02-01 10:00:00;2026-02-05;2026-02-01 10:00:00;Compras Casa;Cartao Nubank
Salario;300000;paid;income;2026-02-02 10:00:00;-;2026-02-02 10:00:00;-;-
CSV;

    $file = UploadedFile::fake()->createWithContent('import.csv', $csv);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson(route('api.csv.import'), [
            'file' => $file,
        ]);

    $response->assertStatus(200);

    $this->assertDatabaseCount('expenses', 2);
    $this->assertDatabaseHas('categories', [
        'name' => 'Compras Casa',
        'user_id' => $user->id,
    ]);

    $this->assertDatabaseHas('expenses', [
        'title' => 'Salario',
        'source_id' => $defaultSourceId,
    ]);

    $this->assertDatabaseHas('sources', [
        'name' => 'Cartao Nubank',
        'user_id' => $user->id,
    ]);

    $customSourceId = Source::query()
        ->where('user_id', $user->id)
        ->where('name', 'Cartao Nubank')
        ->value('id');

    $this->assertDatabaseHas('expenses', [
        'title' => 'Mercado',
        'source_id' => $customSourceId,
    ]);
});

test('deve importar csv reutilizando fonte existente pelo nome', function (): void {
    $user = User::factory()->create();
    $token = authTokenFor($user);

    $existingSource = Source::factory()->create([
        'user_id' => $user->id,
        'name' => 'Inter',
        'is_default' => false,
    ]);

    $csv = <<<'CSV'
TITLE;AMOUNT;STATUS;TYPE;PAYMENT_DATE;DUE_DATE;CREATED_AT;CATEGORY_NAME;SOURCE_NAME
Assinatura;4900;paid;expense;2026-03-01 10:00:00;-;2026-03-01 10:00:00;-;Inter
CSV;

    $file = UploadedFile::fake()->createWithContent('import.csv', $csv);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson(route('api.csv.import'), [
            'file' => $file,
        ]);

    $response->assertStatus(200);

    $this->assertDatabaseCount('sources', 2);
    $this->assertDatabaseHas('expenses', [
        'title' => 'Assinatura',
        'source_id' => $existingSource->id,
    ]);
});

test('deve importar csv com fonte Principal mapeando para carteira principal do usuario', function (): void {
    $user = User::factory()->create();
    $token = authTokenFor($user);
    $defaultSourceId = $user->sources()->where('is_default', true)->value('id');

    $csv = <<<'CSV'
TITLE;AMOUNT;STATUS;TYPE;PAYMENT_DATE;DUE_DATE;CREATED_AT;CATEGORY_NAME;SOURCE_NAME
Mercado;9900;paid;expense;2026-03-01 10:00:00;-;2026-03-01 10:00:00;-;Principal
CSV;

    $file = UploadedFile::fake()->createWithContent('import.csv', $csv);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson(route('api.csv.import'), [
            'file' => $file,
        ]);

    $response->assertStatus(200);

    $this->assertDatabaseHas('expenses', [
        'title' => 'Mercado',
        'source_id' => $defaultSourceId,
    ]);

    $this->assertDatabaseMissing('sources', [
        'user_id' => $user->id,
        'name' => 'Principal',
    ]);
});

test('deve exportar csv com coluna de fonte', function (): void {
    $user = User::factory()->create();
    $token = authTokenFor($user);

    $source = Source::factory()->create([
        'user_id' => $user->id,
        'name' => 'Conta PJ',
        'is_default' => false,
    ]);

    Expense::factory()->create([
        'user_id' => $user->id,
        'source_id' => $source->id,
        'title' => 'Servico',
        'amount' => 90000,
        'status' => 'paid',
        'type' => 'income',
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->get(route('api.csv.export'));

    $response->assertOk();

    $content = $response->streamedContent();
    expect($content)->toContain('SOURCE_NAME');
    expect($content)->toContain('Conta PJ');
    expect($content)->toContain('SOURCE_TYPE');
});
