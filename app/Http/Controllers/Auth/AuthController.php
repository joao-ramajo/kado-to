<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Action\Auth\WebLoginAction;
use App\Action\Auth\WebLogoutAction;
use App\Action\Auth\WebRegisterAction;
use App\DTO\Auth\WebLoginInput;
use App\DTO\Auth\WebLogoutInput;
use App\DTO\Auth\WebRegisterInput;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\WebLoginRequest;
use App\Http\Requests\Auth\WebRegisterRequest;
use App\Support\Logging\FormatsLogMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Psr\Log\LoggerInterface;

class AuthController extends Controller
{
    use FormatsLogMessage;

    public function __construct(
        private readonly WebLoginAction $webLoginAction,
        private readonly WebRegisterAction $webRegisterAction,
        private readonly WebLogoutAction $webLogoutAction,
        private readonly LoggerInterface $logger,
    ) {}

    public function login(WebLoginRequest $request)
    {
        $validated = $request->validated();

        $this->logger->info($this->formatLogMessage('login request received'), [
            'email' => $validated['email'],
            'remember' => $request->has('remember'),
        ]);

        $output = $this->webLoginAction->execute(
            new WebLoginInput(
                email: $validated['email'],
                password: $validated['password'],
                remember: $request->has('remember')
            )
        );

        if ($output->success) {
            $request->session()->regenerate();

            return redirect()->route('web.dashboard');
        }

        return back()->withErrors([
            'email' => $output->errorMessage,
        ])->withInput();
    }

    public function register(WebRegisterRequest $request)
    {
        $validated = $request->validated();
        $this->logger->info($this->formatLogMessage('register request received'), [
            'email' => $validated['email'],
        ]);

        $this->webRegisterAction->execute(
            new WebRegisterInput(
                name: $validated['name'],
                email: $validated['email'],
                password: $validated['password'],
            )
        );

        return redirect()->route('web.dashboard');
    }

    public function logout(Request $request)
    {
        $userId = Auth::id() ?? 0;
        $this->logger->info($this->formatLogMessage('logout request received'), [
            'user_id' => $userId,
        ]);

        $this->webLogoutAction->execute(new WebLogoutInput($userId));

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect(route('web.login'));
    }
}
