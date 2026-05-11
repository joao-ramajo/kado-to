<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Action\Auth\RegisterUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use DomainException;
use Illuminate\Http\JsonResponse;

class RegisterController extends Controller
{
    public function __construct(
        protected readonly RegisterUserAction $registerUserAction
    ) {}

    public function __invoke(RegisterRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            $result = $this->registerUserAction->execute($data);

            return response()->json([
                'message' => 'Conta registrada com sucesso.',
                'user' => [
                    'name' => $result['name'],
                ],
                'token' => $result['token'],
            ], 201);
        } catch (DomainException $domainException) {
            return response()
                ->json([
                    'message' => $domainException->getMessage(),
                ], 400);
        }
    }
}
