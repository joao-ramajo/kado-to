<?php

declare(strict_types=1);

namespace App\Http\Controllers\Expense;

use App\Action\Expense\CreateExpense;
use App\Http\Requests\Expense\CreateExpenseRequest;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CreateExpenseController
{
    public function __construct(
        protected readonly CreateExpense $createExpense
    ) {}

    public function __invoke(CreateExpenseRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            $data['userId'] = Auth::id();

            $this->createExpense->execute($data);

            return response()->json([
                'message' => 'Movimentação registrada com sucesso.',
            ], 201);
        } catch (DomainException $domainException) {
            return response()->json([
                'message' => $domainException->getMessage(),
            ], 400);
        }
    }
}
