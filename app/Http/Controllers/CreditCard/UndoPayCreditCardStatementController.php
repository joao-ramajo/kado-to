<?php

declare(strict_types=1);

namespace App\Http\Controllers\CreditCard;

use App\Action\CreditCard\UndoPayCreditCardStatementAction;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\JsonResponse;

class UndoPayCreditCardStatementController extends Controller
{
    public function __construct(
        private readonly UndoPayCreditCardStatementAction $undoPayCreditCardStatementAction,
    ) {}

    public function __invoke(int $statementId): JsonResponse
    {
        try {
            $this->undoPayCreditCardStatementAction->execute(
                $statementId,
                $this->authenticatedUserId(),
            );

            return response()->json([
                'message' => 'Pagamento da fatura desfeito com sucesso.',
            ], 200);
        } catch (DomainException $domainException) {
            return response()->json([
                'message' => $domainException->getMessage(),
            ], 400);
        }
    }
}
