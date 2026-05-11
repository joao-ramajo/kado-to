<?php

declare(strict_types=1);

namespace App\Http\Controllers\Expense;

use App\Action\Category\GetCategoryListAction;
use App\DTO\Category\GetCategoryListInput;
use App\Http\Controllers\Controller;
use App\Http\Requests\Expense\GetCategoryListRequest;
use App\Support\Logging\FormatsLogMessage;
use Illuminate\Http\JsonResponse;
use Psr\Log\LoggerInterface;

class GetCategoryListController extends Controller
{
    use FormatsLogMessage;

    public function __construct(
        private readonly GetCategoryListAction $getCategoryListAction,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(GetCategoryListRequest $request): JsonResponse
    {
        $userId = $this->authenticatedUserId();
        $month = $request->integer('month');

        $this->logger->info($this->formatLogMessage('request received'), [
            'user_id' => $userId,
            'month_filter' => $month,
        ]);

        $output = $this->getCategoryListAction->execute(
            new GetCategoryListInput($userId, $request->has('month') ? $month : null)
        );

        return response()->json($output->toArray());
    }
}
