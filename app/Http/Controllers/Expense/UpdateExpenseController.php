<?php

declare(strict_types=1);

namespace App\Http\Controllers\Expense;

use App\Action\Expense\UpdateExpenseAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Expense\UpdateExpenseRequest;
use DomainException;
use Illuminate\Http\JsonResponse;

class UpdateExpenseController extends Controller
{
    public function __construct(
        protected readonly UpdateExpenseAction $updateExpenseAction
    ) {}

    public function __invoke(UpdateExpenseRequest $request, int $id): JsonResponse
    {
        try {
            $data = $request->validated();

            $this->updateExpenseAction->execute($data, $id);

            return response()
                ->json([
                    'message' => 'Registro atualizado com sucesso',
                ], 200);
        } catch (DomainException $domainException) {
            return response()
                ->json([
                    'message' => $domainException->getMessage(),
                ], 400);
        }
    }
}
