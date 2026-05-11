<?php

declare(strict_types=1);

namespace App\Http\Controllers\Expense;

use App\Action\Expense\DeleteExpenseAction;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Support\Facades\Auth;

class DeleteExpenseController extends Controller
{
    public function __construct(
        protected readonly DeleteExpenseAction $deleteExpenseAction
    ) {}

    public function __invoke(string $id)
    {
        try {
            $userId = Auth::id();

            $this->deleteExpenseAction->execute(
                (int) $id,
                $userId
            );

            return response()
                ->json([
                    'message' => 'Despesa deletada com sucesso.',
                ], 200);
        } catch (DomainException $e) {
            return response()
                ->json([
                    'message' => $e->getMessage(),
                ], 400);
        }
    }
}
