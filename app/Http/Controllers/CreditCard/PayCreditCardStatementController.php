<?php

declare(strict_types=1);

namespace App\Http\Controllers\CreditCard;

use App\Action\CreditCard\PayCreditCardStatementAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreditCard\PayCreditCardStatementRequest;
use DomainException;
use Illuminate\Http\JsonResponse;

class PayCreditCardStatementController extends Controller
{
    public function __construct(
        private readonly PayCreditCardStatementAction $payCreditCardStatementAction,
    ) {}

    public function __invoke(PayCreditCardStatementRequest $request, int $statementId): JsonResponse
    {
        try {
            $this->payCreditCardStatementAction->execute(
                $statementId,
                $this->authenticatedUserId(),
                $request->integer('payment_source_id'),
            );

            return response()->json([
                'message' => 'Fatura paga com sucesso.',
            ], 200);
        } catch (DomainException $domainException) {
            return response()->json([
                'message' => $domainException->getMessage(),
            ], 400);
        }
    }
}
