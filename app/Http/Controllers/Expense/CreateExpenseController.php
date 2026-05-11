<?php

declare(strict_types=1);

namespace App\Http\Controllers\Expense;

use App\Action\Expense\CreateExpense;
use App\Http\Controllers\Controller;
use App\Http\Requests\Expense\CreateExpenseRequest;
use DomainException;
use Illuminate\Http\JsonResponse;

class CreateExpenseController extends Controller
{
    public function __construct(
        protected readonly CreateExpense $createExpense
    ) {}

    public function __invoke(CreateExpenseRequest $request): JsonResponse
    {
        try {
            /** @var array{
             *     title: string,
             *     amount: int,
             *     type: string,
             *     status: string,
             *     category_id?: int|null,
             *     source_id?: int|null,
             *     purchase_date?: string|null,
             *     payment_date?: string|null,
             *     installment_total?: int|null
             * } $data
             */
            $data = $request->validated();

            $data['userId'] = $this->authenticatedUserId();

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
