<?php

declare(strict_types=1);

namespace App\Http\Controllers\Expense;

use App\Action\Expense\UpdateExpenseAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Expense\UpdateExpenseRequest;
use DomainException;

class UpdateExpenseController extends Controller
{
    public function __construct(
        protected readonly UpdateExpenseAction $updateExpenseAction
    ) {}

    public function __invoke(UpdateExpenseRequest $request, int $id)
    {
        try {
            $data = $request->validated();

            $this->updateExpenseAction->execute($data, $id);

            return response()
                ->json([
                    'message' => 'Registro atualizado com sucesso',
                ], 200);
        } catch (DomainException $e) {
            return response()
                ->json([
                    'message' => $e->getMessage(),
                ], 400);
        }
    }
}
