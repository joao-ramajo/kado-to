<?php

declare(strict_types=1);

namespace App\Http\Controllers\Expense;

use App\Action\Expense\MarkExpenseAsPaidAction;
use App\DTO\Expense\MarkExpenseAsPaidInput;
use App\Http\Controllers\Controller;
use App\Support\Logging\FormatsLogMessage;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Psr\Log\LoggerInterface;

class MarkExpenseAsPaidController extends Controller
{
    use FormatsLogMessage;

    public function __construct(
        private readonly MarkExpenseAsPaidAction $markExpenseAsPaidAction,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(string $id)
    {
        try {
            $expenseId = (int) $id;
            $userId = Auth::id();
            $this->logger->info($this->formatLogMessage('request received'), [
                'user_id' => $userId,
                'expense_id' => $expenseId,
            ]);

            $output = $this->markExpenseAsPaidAction->execute(
                new MarkExpenseAsPaidInput($expenseId, $userId)
            );

            return response()->json($output->toArray(), 200);
        } catch (DomainException $e) {
            return response()
                ->json([
                    'message' => $e->getMessage(),
                ], 400);
        }
    }
}
