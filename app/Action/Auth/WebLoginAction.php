<?php

declare(strict_types=1);

namespace App\Action\Auth;

use App\DTO\Auth\WebLoginInput;
use App\DTO\Auth\WebLoginOutput;
use App\Support\Logging\FormatsLogMessage;
use Illuminate\Support\Facades\Auth;
use Psr\Log\LoggerInterface;

class WebLoginAction
{
    use FormatsLogMessage;

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(WebLoginInput $input): WebLoginOutput
    {
        $this->logger->info($this->formatLogMessage('started'), [
            'email' => $input->email,
            'remember' => $input->remember,
        ]);

        $success = Auth::attempt([
            'email' => $input->email,
            'password' => $input->password,
        ], $input->remember);

        if (! $success) {
            $this->logger->warning($this->formatLogMessage('failed'), [
                'email' => $input->email,
            ]);

            return new WebLoginOutput(false, 'Credenciais inválidas.');
        }

        $this->logger->info($this->formatLogMessage('completed'), [
            'user_id' => Auth::id(),
        ]);

        return new WebLoginOutput(true);
    }
}
