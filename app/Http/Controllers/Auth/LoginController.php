<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Action\Auth\LoginAction;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    public function __construct(
        protected readonly LoginAction $loginAction
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            /** @var array{email: string, password: string, remember?: bool} $credentials */
            $credentials = $request->validate([
                'email' => ['required', 'email'],
                'password' => ['required'],
                'remember' => ['nullable', 'boolean'],
            ]);

            $result = $this->loginAction->execute($credentials);

            return response()->json([
                'message' => 'Login realizado com sucesso.',
                'user' => [
                    'name' => $result['name'],
                ],
                'token' => $result['token'],
            ], 200);
        } catch (DomainException $domainException) {
            return response()->json([
                'message' => $domainException->getMessage(),
            ], 400);
        }
    }
}
