<?php

declare(strict_types=1);

namespace App\Http\Controllers\Expense;

use App\Action\Category\UpdateCategoryAction;
use App\DTO\Category\UpdateCategoryInput;
use App\Http\Controllers\Controller;
use App\Http\Requests\Expense\UpdateCategoryRequest;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class UpdateCategoryController extends Controller
{
    public function __construct(
        private readonly UpdateCategoryAction $updateCategoryAction,
    ) {}

    public function __invoke(UpdateCategoryRequest $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validated();

            $input = new UpdateCategoryInput(
                id: $id,
                userId: Auth::id(),
                name: $validated['name'],
                color: $validated['color'],
            );

            $output = $this->updateCategoryAction->execute($input);

            return response()->json($output->toArray(), 200);
        } catch (DomainException $domainException) {
            return response()->json([
                'message' => $domainException->getMessage(),
            ], 400);
        }
    }
}
