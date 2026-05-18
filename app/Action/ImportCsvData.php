<?php

declare(strict_types=1);

namespace App\Action;

use App\Models\CreditCardStatement;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Source;
use App\Support\CreditCard\CreditCardStatementService;
use App\Support\Logging\FormatsLogMessage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Psr\Log\LoggerInterface;
use Throwable;

class ImportCsvData
{
    use FormatsLogMessage;

    /** @var list<string> */
    private const DEFAULT_SOURCE_ALIASES = [
        'principal',
        'carteira principal',
        'fonte principal',
    ];

    /** @var list<string> */
    private const REQUIRED_HEADERS = [
        'TITLE', 'AMOUNT', 'STATUS', 'TYPE', 'PAYMENT_DATE',
        'DUE_DATE', 'CREATED_AT', 'CATEGORY_NAME', 'SOURCE_NAME',
    ];

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly CreditCardStatementService $creditCardStatementService,
    ) {}

    public function execute(UploadedFile $file): bool
    {
        $userId = Auth::id();
        if (! is_int($userId)) {
            return false;
        }

        $startedAt = microtime(true);
        $this->logger->info($this->formatLogMessage('started'), [
            'user_id' => $userId,
            'file' => $file->getClientOriginalName(),
        ]);

        $validator = Validator::make(
            ['file' => $file],
            ['file' => ['required', 'file', 'mimes:csv,txt', 'max:10240']]
        );

        if ($validator->fails()) {
            $this->logger->warning($this->formatLogMessage('validation failed'), [
                'user_id' => $userId,
            ]);

            return false;
        }

        try {
            DB::beginTransaction();

            // 👇 ESSENCIAL EM PRODUÇÃO
            ini_set('auto_detect_line_endings', '1');

            $handle = fopen($file->getRealPath(), 'r');
            if (! $handle) {
                return false;
            }

            $header = fgetcsv($handle, 0, ';');
            if ($header === false || ! isset($header[0])) {
                fclose($handle);

                return false;
            }

            $header[0] = preg_replace('/^\x{FEFF}/u', '', $header[0]);
            /** @var list<string> $header */
            $header = array_map(static fn (?string $value): string => (string) $value, $header);

            if (! $this->validateHeaders($header)) {
                fclose($handle);

                return false;
            }

            $batch = [];
            $statementIdsToSync = [];

            while (($data = fgetcsv($handle, 0, ';')) !== false) {
                // 👇 proteção contra linhas quebradas
                if (count($data) !== count($header)) {
                    $this->logger->warning($this->formatLogMessage('invalid csv row ignored'), [
                        'data' => $data,
                    ]);

                    continue;
                }

                // 👇 normaliza encoding
                /** @var list<string> $data */
                $data = array_map(
                    static function (?string $v): string {
                        $converted = mb_convert_encoding((string) $v, 'UTF-8', 'UTF-8,ISO-8859-1,WINDOWS-1252');

                        return trim($converted !== false ? $converted : '');
                    },
                    $data
                );

                $row = array_combine($header, $data);

                if ($this->isDuplicate($row)) {
                    continue;
                }

                $category = $this->findOrCreateCategory($row['CATEGORY_NAME']);
                $sourceId = $this->findOrCreateSourceId(
                    sourceName: $row['SOURCE_NAME'] ?? null,
                    sourceType: $row['SOURCE_TYPE'] ?? null,
                    sourceColor: $row['SOURCE_COLOR'] ?? null,
                    allowNegative: $row['SOURCE_ALLOW_NEGATIVE'] ?? null,
                    creditLimit: $row['SOURCE_CREDIT_LIMIT'] ?? null,
                    statementClosingDay: $row['SOURCE_STATEMENT_CLOSING_DAY'] ?? null,
                    statementDueDay: $row['SOURCE_STATEMENT_DUE_DAY'] ?? null,
                );
                $cardSourceId = $this->resolveCardSourceId($row, $sourceId);
                $statementId = $this->findOrCreateStatementId($row, $cardSourceId);

                $batch[] = [
                    'title' => $row['TITLE'],
                    'amount' => (int) $row['AMOUNT'],
                    'status' => $row['STATUS'],
                    'type' => $row['TYPE'],
                    'origin_type' => $this->readValue($row, 'ORIGIN_TYPE') ?? Expense::ORIGIN_DIRECT,
                    'occurrence_type' => $this->readValue($row, 'OCCURRENCE_TYPE') ?? Expense::OCCURRENCE_DIRECT,
                    'payment_date' => $row['PAYMENT_DATE'] !== '-' ? $row['PAYMENT_DATE'] : null,
                    'purchase_date' => $this->nullIfPlaceholder($this->readValue($row, 'PURCHASE_DATE')),
                    'due_date' => $row['DUE_DATE'] !== '-' ? $row['DUE_DATE'] : null,
                    'credit_card_statement_id' => $statementId,
                    'installment_group_id' => $this->nullIfPlaceholder($this->readValue($row, 'INSTALLMENT_GROUP_ID')),
                    'installment_number' => $this->normalizeInteger($this->readValue($row, 'INSTALLMENT_NUMBER')),
                    'installment_total' => $this->normalizeInteger($this->readValue($row, 'INSTALLMENT_TOTAL')),
                    'created_at' => $row['CREATED_AT'],
                    'updated_at' => now(),
                    'user_id' => $userId,
                    'category_id' => $category?->id,
                    'source_id' => $sourceId,
                ];

                if (is_int($statementId)) {
                    $statementIdsToSync[$statementId] = $statementId;
                    $this->syncStatementPayment($statementId, $row, $sourceId);
                }

                if (count($batch) >= 100) {
                    DB::table('expenses')->insert($batch);
                    $batch = [];
                }
            }

            if ($batch !== []) {
                DB::table('expenses')->insert($batch);
            }

            fclose($handle);

            foreach ($statementIdsToSync as $statementId) {
                $this->creditCardStatementService->syncById($statementId);
            }

            DB::commit();

            $this->logger->info($this->formatLogMessage('completed'), [
                'user_id' => $userId,
                'import_time_ms' => (int) ((microtime(true) - $startedAt) * 1000),
            ]);

            return true;
        } catch (Throwable $throwable) {
            DB::rollBack();
            $this->logger->error($this->formatLogMessage('failed'), [
                'user_id' => $userId,
                'error' => $throwable->getMessage(),
            ]);

            return false;
        }
    }

    /** @param list<string> $headers */
    private function validateHeaders(array $headers): bool
    {
        foreach (self::REQUIRED_HEADERS as $required) {
            if (! in_array($required, $headers)) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, string> $row */
    private function isDuplicate(array $row): bool
    {
        $userId = Auth::id();
        if (! is_int($userId)) {
            return false;
        }

        return DB::table('expenses')
            ->where('amount', $row['AMOUNT'])
            ->where('created_at', $row['CREATED_AT'])
            ->where('user_id', $userId)
            ->exists();
    }

    private function findOrCreateCategory(?string $categoryName): ?Category
    {
        if (! $categoryName || $categoryName === '-') {
            return null;
        }

        $category = Category::query()->where('name', $categoryName)
            ->where(function ($query): void {
                $userId = Auth::id();
                $query
                    ->whereNull('user_id')
                    ->orWhere('user_id', is_int($userId) ? $userId : 0);
            })
            ->first();

        if (! $category) {
            $userId = Auth::id();
            if (! is_int($userId)) {
                return null;
            }

            return Category::query()->create([
                'name' => $categoryName,
                'user_id' => $userId,
            ]);
        }

        return $category;
    }

    private function findOrCreateSourceId(
        ?string $sourceName,
        ?string $sourceType = null,
        ?string $sourceColor = null,
        ?string $allowNegative = null,
        ?string $creditLimit = null,
        ?string $statementClosingDay = null,
        ?string $statementDueDay = null,
        bool $useDefaultAlias = true,
    ): ?int
    {
        $userId = Auth::id();
        if (! is_int($userId)) {
            return null;
        }

        $normalizedSourceName = trim((string) $sourceName);
        $normalizedSourceAlias = mb_strtolower($normalizedSourceName);

        if (
            $normalizedSourceName === ''
            || $normalizedSourceName === '-'
            || ($useDefaultAlias && in_array($normalizedSourceAlias, self::DEFAULT_SOURCE_ALIASES, true))
        ) {
            return $this->getDefaultSourceId();
        }

        $metadata = $this->buildSourceMetadata(
            $sourceType,
            $sourceColor,
            $allowNegative,
            $creditLimit,
            $statementClosingDay,
            $statementDueDay,
        );

        $source = Source::query()
            ->where('user_id', $userId)
            ->where('name', $normalizedSourceName)
            ->first();

        if (! $source) {
            $source = Source::query()->create(array_merge([
                'user_id' => $userId,
                'name' => $normalizedSourceName,
                'is_default' => false,
            ], $metadata));
        } else {
            $source->fill($metadata);
            $source->save();
        }

        return $source->id;
    }

    /** @param array<string, string> $row */
    private function resolveCardSourceId(array $row, ?int $expenseSourceId): ?int
    {
        $cardSourceName = $this->readValue($row, 'CARD_SOURCE_NAME');

        if ($cardSourceName !== null) {
            return $this->findOrCreateSourceId(
                sourceName: $cardSourceName,
                sourceType: Source::TYPE_CREDIT_CARD,
                sourceColor: $this->readValue($row, 'CARD_SOURCE_COLOR'),
                allowNegative: '0',
                creditLimit: $this->readValue($row, 'CARD_SOURCE_CREDIT_LIMIT'),
                statementClosingDay: $this->readValue($row, 'CARD_SOURCE_STATEMENT_CLOSING_DAY'),
                statementDueDay: $this->readValue($row, 'CARD_SOURCE_STATEMENT_DUE_DAY'),
                useDefaultAlias: false,
            );
        }

        $occurrenceType = $this->readValue($row, 'OCCURRENCE_TYPE');
        $originType = $this->readValue($row, 'ORIGIN_TYPE');

        if (
            $expenseSourceId !== null
            && ($occurrenceType === Expense::OCCURRENCE_PURCHASE || $originType === Expense::ORIGIN_CREDIT_CARD)
        ) {
            return $expenseSourceId;
        }

        return null;
    }

    /** @param array<string, string> $row */
    private function findOrCreateStatementId(array $row, ?int $cardSourceId): ?int
    {
        if ($cardSourceId === null) {
            return null;
        }

        $referenceMonth = $this->readValue($row, 'STATEMENT_REFERENCE_MONTH');
        if ($referenceMonth === null) {
            return null;
        }

        $statement = CreditCardStatement::query()
            ->where('source_id', $cardSourceId)
            ->whereDate('reference_month', $referenceMonth)
            ->first();

        $attributes = [
            'closing_at' => $this->readValue($row, 'STATEMENT_CLOSING_AT') ?? $this->readValue($row, 'DUE_DATE') ?? $referenceMonth,
            'due_at' => $this->readValue($row, 'STATEMENT_DUE_AT') ?? $this->readValue($row, 'DUE_DATE') ?? $referenceMonth,
        ];

        if ($statement === null) {
            $statement = CreditCardStatement::query()->create([
                'source_id' => $cardSourceId,
                'reference_month' => $referenceMonth,
                'closing_at' => $attributes['closing_at'],
                'due_at' => $attributes['due_at'],
                'status' => CreditCardStatement::STATUS_OPEN,
                'total_amount' => 0,
            ]);
        } else {
            $statement->fill($attributes);
            $statement->save();
        }

        return $statement->id;
    }

    /** @param array<string, string> $row */
    private function syncStatementPayment(int $statementId, array $row, ?int $paymentSourceId): void
    {
        if ($this->readValue($row, 'OCCURRENCE_TYPE') !== Expense::OCCURRENCE_INVOICE_PAYMENT) {
            return;
        }

        if ($paymentSourceId === null) {
            return;
        }

        CreditCardStatement::query()
            ->whereKey($statementId)
            ->update([
                'paid_at' => $this->readValue($row, 'STATEMENT_PAID_AT')
                    ?? $this->readValue($row, 'PAYMENT_DATE')
                    ?? now(),
                'payment_source_id' => $paymentSourceId,
            ]);
    }

    private function buildSourceMetadata(
        ?string $sourceType,
        ?string $sourceColor,
        ?string $allowNegative,
        ?string $creditLimit,
        ?string $statementClosingDay,
        ?string $statementDueDay,
    ): array {
        $type = $this->nullIfPlaceholder($sourceType) ?? Source::TYPE_CASH_LIKE;
        $isCreditCard = $type === Source::TYPE_CREDIT_CARD;

        return [
            'type' => $type,
            'color' => $this->nullIfPlaceholder($sourceColor) ?? '#64748B',
            'allow_negative' => $this->normalizeBoolean($allowNegative),
            'credit_limit' => $isCreditCard ? $this->normalizeInteger($creditLimit) : null,
            'statement_closing_day' => $isCreditCard ? $this->normalizeInteger($statementClosingDay) : null,
            'statement_due_day' => $isCreditCard ? $this->normalizeInteger($statementDueDay) : null,
        ];
    }

    /** @param array<string, string> $row */
    private function readValue(array $row, string $key): ?string
    {
        if (! array_key_exists($key, $row)) {
            return null;
        }

        return $this->nullIfPlaceholder($row[$key]);
    }

    private function nullIfPlaceholder(?string $value): ?string
    {
        $normalized = trim((string) $value);

        if ($normalized === '' || $normalized === '-') {
            return null;
        }

        return $normalized;
    }

    private function normalizeInteger(?string $value): ?int
    {
        $normalized = $this->nullIfPlaceholder($value);

        if ($normalized === null || ! is_numeric($normalized)) {
            return null;
        }

        return (int) $normalized;
    }

    private function normalizeBoolean(?string $value): bool
    {
        $normalized = mb_strtolower((string) $this->nullIfPlaceholder($value));

        return in_array($normalized, ['1', 'true', 'yes', 'sim'], true);
    }

    private function getDefaultSourceId(): ?int
    {
        $userId = Auth::id();
        if (! is_int($userId)) {
            return null;
        }

        $sourceId = DB::table('sources')
            ->where('user_id', $userId)
            ->where('is_default', true)
            ->value('id');

        return is_int($sourceId) ? $sourceId : null;
    }
}
