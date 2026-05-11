<?php

declare(strict_types=1);

namespace App\Action;

use Throwable;
use App\Models\Category;
use App\Models\Source;
use App\Support\Logging\FormatsLogMessage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Psr\Log\LoggerInterface;

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

            $header[0] = preg_replace('/^\x{FEFF}/u', '', (string) $header[0]);
            /** @var list<string> $header */
            $header = array_map(static fn (?string $value): string => (string) $value, $header);

            if (! $this->validateHeaders($header)) {
                fclose($handle);

                return false;
            }

            $batch = [];

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
                /** @var array<string, string> $row */
                $row = $row;

                if ($this->isDuplicate($row)) {
                    continue;
                }

                $category = $this->findOrCreateCategory($row['CATEGORY_NAME']);
                $sourceId = $this->findOrCreateSourceId($row['SOURCE_NAME'] ?? null);

                $batch[] = [
                    'title' => $row['TITLE'],
                    'amount' => (int) $row['AMOUNT'],
                    'status' => $row['STATUS'],
                    'type' => $row['TYPE'],
                    'payment_date' => $row['PAYMENT_DATE'] !== '-' ? $row['PAYMENT_DATE'] : null,
                    'due_date' => $row['DUE_DATE'] !== '-' ? $row['DUE_DATE'] : null,
                    'created_at' => $row['CREATED_AT'],
                    'updated_at' => now(),
                    'user_id' => $userId,
                    'category_id' => $category?->id,
                    'source_id' => $sourceId,
                ];

                if (count($batch) >= 100) {
                    DB::table('expenses')->insert($batch);
                    $batch = [];
                }
            }

            if ($batch !== []) {
                DB::table('expenses')->insert($batch);
            }

            fclose($handle);
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

    private function findOrCreateSourceId(?string $sourceName): ?int
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
            || in_array($normalizedSourceAlias, self::DEFAULT_SOURCE_ALIASES, true)
        ) {
            return $this->getDefaultSourceId();
        }

        $source = Source::query()
            ->where('user_id', $userId)
            ->where('name', $normalizedSourceName)
            ->first();

        if (! $source) {
            $source = Source::query()->create([
                'user_id' => $userId,
                'name' => $normalizedSourceName,
                'color' => '#64748B',
                'is_default' => false,
                'allow_negative' => false,
            ]);
        }

        return $source->id;
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
