<?php

declare(strict_types=1);

namespace App\Action\Source;

use App\DTO\Source\CreateSourceInput;
use App\DTO\Source\CreateSourceOutput;
use App\Models\Source;
use App\Support\Logging\FormatsLogMessage;
use Psr\Log\LoggerInterface;

class CreateSourceAction
{
    use FormatsLogMessage;

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(CreateSourceInput $input): CreateSourceOutput
    {
        $this->logger->info($this->formatLogMessage('started'), [
            'user_id' => $input->userId,
            'name' => $input->name,
            'type' => $input->type,
            'allow_negative' => $input->allowNegative,
        ]);

        $source = Source::create([
            'user_id' => $input->userId,
            'name' => $input->name,
            'type' => $input->type,
            'color' => $input->color,
            'allow_negative' => $input->type === Source::TYPE_CREDIT_CARD ? false : $input->allowNegative,
            'is_default' => false,
            'credit_limit' => $input->creditLimit,
            'statement_closing_day' => $input->statementClosingDay,
            'statement_due_day' => $input->statementDueDay,
        ]);

        $this->logger->info($this->formatLogMessage('completed'), [
            'user_id' => $input->userId,
            'source_id' => $source->id,
        ]);

        return new CreateSourceOutput('Fonte criada com sucesso', $source);
    }
}
