<?php

declare(strict_types=1);

namespace App\Http\Controllers\Expense;

use App\Action\Expense\DeleteExpenseAction;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\JsonResponse;

class DeleteExpenseController extends Controller
{
    public function __construct(
        protected readonly DeleteExpenseAction $deleteExpenseAction
    ) {}

    public function __invoke(string $id): JsonResponse
    {
        try {
            $userId = $this->authenticatedUserId();

            $this->deleteExpenseAction->execute(
                (int) $id,
                $userId
            );

            return response()
                ->json([
                    'message' => 'Despesa deletada com sucesso.',
                ], 200);
        } catch (DomainException $domainException) {
            return response()
                ->json([
                    'message' => $domainException->getMessage(),
                ], 400);
        }
    }
}
