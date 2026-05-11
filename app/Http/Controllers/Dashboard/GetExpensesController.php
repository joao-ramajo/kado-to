<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Action\Dashboard\GetExpensesAction;
use App\DTO\Dashboard\GetExpensesInput;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\GetExpensesRequest;
use App\Support\Logging\FormatsLogMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Psr\Log\LoggerInterface;

class GetExpensesController extends Controller
{
    use FormatsLogMessage;

    public function __construct(
        private readonly GetExpensesAction $getExpensesAction,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(GetExpensesRequest $request): JsonResponse
    {
        $userId = Auth::id();
        $status = $request->validated('status');
        $query = $request->validated('query');
        $categoryId = $request->validated('category_id');
        $month = $request->validated('month');

        $this->logger->info($this->formatLogMessage('request received'), [
            'user_id' => $userId,
            'status_filter' => $status,
            'query_filter' => $query,
            'category_id_filter' => $categoryId,
            'month_filter' => $month,
        ]);

        $output = $this->getExpensesAction->execute(
            new GetExpensesInput(
                $userId,
                $status,
                $query,
                $categoryId !== null ? (int) $categoryId : null,
                $month !== null ? (int) $month : null,
            )
        );

        return response()->json($output->toArray());
    }
}
