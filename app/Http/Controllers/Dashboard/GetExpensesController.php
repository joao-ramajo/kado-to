<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Action\Dashboard\GetExpensesAction;
use App\DTO\Dashboard\GetExpensesInput;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\GetExpensesRequest;
use App\Support\Logging\FormatsLogMessage;
use Illuminate\Http\JsonResponse;
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
        $userId = $this->authenticatedUserId();
        $status = $request->has('status') ? $request->string('status')->toString() : null;
        $query = $request->has('query') ? $request->string('query')->toString() : null;
        $categoryId = $request->has('category_id') ? $request->integer('category_id') : null;
        $sourceId = $request->has('source_id') ? $request->integer('source_id') : null;
        $month = $request->has('month') ? $request->integer('month') : null;

        $this->logger->info($this->formatLogMessage('request received'), [
            'user_id' => $userId,
            'status_filter' => $status,
            'query_filter' => $query,
            'category_id_filter' => $categoryId,
            'source_id_filter' => $sourceId,
            'month_filter' => $month,
        ]);

        $output = $this->getExpensesAction->execute(
            new GetExpensesInput(
                $userId,
                $status,
                $query,
                $categoryId,
                $sourceId,
                $month,
            )
        );

        return response()->json($output->toArray());
    }
}
