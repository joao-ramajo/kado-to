<?php

declare(strict_types=1);

namespace App\Action\Source;

use App\DTO\Source\GetSourceListInput;
use App\DTO\Source\GetSourceListOutput;
use App\Models\Source;
use App\Support\Logging\FormatsLogMessage;
use Psr\Log\LoggerInterface;

class GetSourceListAction
{
    use FormatsLogMessage;

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(GetSourceListInput $input): GetSourceListOutput
    {
        $this->logger->info($this->formatLogMessage('started'), [
            'user_id' => $input->userId,
        ]);

        /** @var list<array<string, mixed>> $sources */
        $sources = array_values(Source::query()->where('user_id', $input->userId)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(static fn (Source $source): array => $source->toArray())
            ->values()
            ->all());

        $this->logger->info($this->formatLogMessage('completed'), [
            'user_id' => $input->userId,
            'count' => count($sources),
        ]);

        return new GetSourceListOutput($sources);
    }
}
