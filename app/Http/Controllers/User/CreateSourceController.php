<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Action\Source\CreateSourceAction;
use App\DTO\Source\CreateSourceInput;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\CreateSourceRequest;
use App\Support\Logging\FormatsLogMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Psr\Log\LoggerInterface;

class CreateSourceController extends Controller
{
    use FormatsLogMessage;

    public function __construct(
        private readonly CreateSourceAction $createSourceAction,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(CreateSourceRequest $request): JsonResponse
    {
        $userId = Auth::id();
        $validated = $request->validated();

        $this->logger->info($this->formatLogMessage('request received'), [
            'user_id' => $userId,
            'name' => $validated['name'],
        ]);

        $input = new CreateSourceInput(
            userId: $userId,
            name: $validated['name'],
            type: $validated['type'] ?? 'cash_like',
            color: $validated['color'],
            allowNegative: $validated['allow_negative'] ?? false,
            creditLimit: $validated['credit_limit'] ?? null,
            statementClosingDay: $validated['statement_closing_day'] ?? null,
            statementDueDay: $validated['statement_due_day'] ?? null,
        );

        $output = $this->createSourceAction->execute($input);

        return response()->json($output->toArray(), 201);
    }
}
