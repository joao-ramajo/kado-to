<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Action\Xlsx\GenerateExpensesXlsxAction;
use App\DTO\Xlsx\GenerateExpensesXlsxInput;
use App\Support\Logging\FormatsLogMessage;
use Illuminate\Support\Facades\Auth;
use Psr\Log\LoggerInterface;

class GenerateExpensesXlsx
{
    use FormatsLogMessage;

    public function __construct(
        private readonly GenerateExpensesXlsxAction $generateExpensesXlsxAction,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke()
    {
        $userId = Auth::id();
        $this->logger->info($this->formatLogMessage('request received'), [
            'user_id' => $userId,
        ]);

        $output = $this->generateExpensesXlsxAction->execute(
            new GenerateExpensesXlsxInput($userId)
        );

        return $output->response;
    }
}
