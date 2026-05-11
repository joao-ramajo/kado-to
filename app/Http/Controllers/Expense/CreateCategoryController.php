<?php

declare(strict_types=1);

namespace App\Http\Controllers\Expense;

use App\Action\Category\CreateCategoryAction;
use App\DTO\Category\CreateCategoryInput;
use App\Http\Controllers\Controller;
use App\Http\Requests\Expense\CreateCategoryRequest;
use App\Support\Logging\FormatsLogMessage;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Psr\Log\LoggerInterface;

class CreateCategoryController extends Controller
{
    use FormatsLogMessage;

    public function __construct(
        private readonly CreateCategoryAction $createCategoryAction,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(CreateCategoryRequest $request)
    {
        try {
            $validated = $request->validated();
            $userId = Auth::id();

            $this->logger->info($this->formatLogMessage('request received'), [
                'user_id' => $userId,
                'name' => $validated['name'],
            ]);

            $input = new CreateCategoryInput(
                userId: $userId,
                name: $validated['name'],
                color: $validated['color'],
            );

            $output = $this->createCategoryAction->execute($input);

            return response()->json($output->toArray(), 201);
        } catch (DomainException $e) {
            return response()
                ->json([
                    'message' => $e->getMessage(),
                ], 400);
        }
    }
}
