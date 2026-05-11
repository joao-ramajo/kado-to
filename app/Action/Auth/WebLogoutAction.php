<?php

declare(strict_types=1);

namespace App\Action\Auth;

use App\DTO\Auth\WebLogoutInput;
use App\DTO\Auth\WebLogoutOutput;
use App\Support\Logging\FormatsLogMessage;
use Illuminate\Support\Facades\Auth;
use Psr\Log\LoggerInterface;

class WebLogoutAction
{
    use FormatsLogMessage;

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(WebLogoutInput $input): WebLogoutOutput
    {
        $this->logger->info($this->formatLogMessage('started'), [
            'user_id' => $input->userId,
        ]);

        Auth::logout();

        $this->logger->info($this->formatLogMessage('completed'), [
            'user_id' => $input->userId,
        ]);

        return new WebLogoutOutput(true);
    }
}
