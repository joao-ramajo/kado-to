<?php

declare(strict_types=1);

namespace App\Action\Auth;

use App\DTO\Auth\WebRegisterInput;
use App\DTO\Auth\WebRegisterOutput;
use App\Events\UserRegistered;
use App\Models\User;
use App\Support\Logging\FormatsLogMessage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Psr\Log\LoggerInterface;

class WebRegisterAction
{
    use FormatsLogMessage;

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(WebRegisterInput $input): WebRegisterOutput
    {
        $this->logger->info($this->formatLogMessage('started'), [
            'email' => $input->email,
        ]);

        $user = User::create([
            'name' => $input->name,
            'email' => $input->email,
            'password' => Hash::make($input->password),
        ]);

        Auth::login($user);
        event(new UserRegistered($user->name, $user->email));

        $this->logger->info($this->formatLogMessage('completed'), [
            'user_id' => $user->id,
        ]);

        return new WebRegisterOutput($user);
    }
}
