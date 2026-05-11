<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Action\Xlsx\GenerateExpensesXlsxAction;
use App\DTO\Xlsx\GenerateExpensesXlsxInput;
use App\Support\Logging\FormatsLogMessage;
use Psr\Log\LoggerInterface;

class GenerateExpensesXlsx extends Controller
{
    use FormatsLogMessage;

    public function __construct(
        private readonly GenerateExpensesXlsxAction $generateExpensesXlsxAction,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(): StreamedResponse
    {
        $userId = $this->authenticatedUserId();
        $this->logger->info($this->formatLogMessage('request received'), [
            'user_id' => $userId,
        ]);

        $output = $this->generateExpensesXlsxAction->execute(
            new GenerateExpensesXlsxInput($userId)
        );

        return $output->response;
    }
}
