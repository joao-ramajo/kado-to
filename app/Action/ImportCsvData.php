<?php

declare(strict_types=1);

namespace App\Action;

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

    private const DEFAULT_SOURCE_ALIASES = [
        'principal',
        'carteira principal',
        'fonte principal',
    ];

    private const REQUIRED_HEADERS = [
        'TITLE', 'AMOUNT', 'STATUS', 'TYPE', 'PAYMENT_DATE',
        'DUE_DATE', 'CREATED_AT', 'CATEGORY_NAME', 'SOURCE_NAME',
    ];

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(UploadedFile $file): bool
    {
        $startedAt = microtime(true);
        $this->logger->info($this->formatLogMessage('started'), [
            'user_id' => Auth::id(),
            'file' => $file->getClientOriginalName(),
        ]);

        $validator = Validator::make(
            ['file' => $file],
            ['file' => 'required|file|mimes:csv,txt|max:10240']
        );

        if ($validator->fails()) {
            $this->logger->warning($this->formatLogMessage('validation failed'), [
                'user_id' => Auth::id(),
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
            $header[0] = preg_replace('/^\x{FEFF}/u', '', $header[0]);

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
                $data = array_map(
                    fn ($v) => trim(
                        mb_convert_encoding($v, 'UTF-8', 'UTF-8,ISO-8859-1,WINDOWS-1252')
                    ),
                    $data
                );

                $row = array_combine($header, $data);

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
                    'user_id' => Auth::id(),
                    'category_id' => $category?->id,
                    'source_id' => $sourceId,
                ];

                if (count($batch) >= 100) {
                    DB::table('expenses')->insert($batch);
                    $batch = [];
                }
            }

            if (! empty($batch)) {
                DB::table('expenses')->insert($batch);
            }

            fclose($handle);
            DB::commit();

            $this->logger->info($this->formatLogMessage('completed'), [
                'user_id' => Auth::id(),
                'import_time_ms' => (int) ((microtime(true) - $startedAt) * 1000),
            ]);

            return true;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->logger->error($this->formatLogMessage('failed'), [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function validateHeaders(array $headers): bool
    {
        foreach (self::REQUIRED_HEADERS as $required) {
            if (! in_array($required, $headers)) {
                return false;
            }
        }

        return true;
    }

    private function isDuplicate(array $row): bool
    {
        return DB::table('expenses')
            ->where('amount', $row['AMOUNT'])
            ->where('created_at', $row['CREATED_AT'])
            ->where('user_id', Auth::id())
            ->exists();
    }

    private function findOrCreateCategory(?string $categoryName): ?Category
    {
        if (! $categoryName || $categoryName === '-') {
            return null;
        }

        $category = Category::where('name', $categoryName)
            ->where(function ($query) {
                $query
                    ->whereNull('user_id')
                    ->orWhere('user_id', Auth::id());
            })
            ->first();

        if (! $category) {
            $category = Category::create([
                'name' => $categoryName,
                'user_id' => Auth::id(),
            ]);
        }

        return $category;
    }

    private function findOrCreateSourceId(?string $sourceName): ?int
    {
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
            ->where('user_id', Auth::id())
            ->where('name', $normalizedSourceName)
            ->first();

        if (! $source) {
            $source = Source::query()->create([
                'user_id' => Auth::id(),
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
        return DB::table('sources')
            ->where('user_id', Auth::id())
            ->where('is_default', true)
            ->value('id');
    }
}
