<?php

declare(strict_types=1);

namespace App\Http\Controllers\Expense;

use App\Action\Category\GetCategoryListAction;
use App\DTO\Category\GetCategoryListInput;
use App\Http\Controllers\Controller;
use App\Http\Requests\Expense\GetCategoryListRequest;
use App\Support\Logging\FormatsLogMessage;
use Illuminate\Support\Facades\Auth;
use Psr\Log\LoggerInterface;

class GetCategoryListController extends Controller
{
    use FormatsLogMessage;

    public function __construct(
        private readonly GetCategoryListAction $getCategoryListAction,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(GetCategoryListRequest $request)
    {
        $userId = Auth::id();
        $month = $request->validated('month');

        $this->logger->info($this->formatLogMessage('request received'), [
            'user_id' => $userId,
            'month_filter' => $month,
        ]);

        $output = $this->getCategoryListAction->execute(
            new GetCategoryListInput($userId, $month !== null ? (int) $month : null)
        );

        return response()->json($output->toArray());
    }
}
