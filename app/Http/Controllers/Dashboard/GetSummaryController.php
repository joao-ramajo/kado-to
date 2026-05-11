<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Action\Dashboard\GetSummaryAction;
use App\DTO\Dashboard\GetSummaryInput;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Logging\FormatsLogMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Psr\Log\LoggerInterface;

class GetSummaryController extends Controller
{
    use FormatsLogMessage;

    public function __construct(
        private readonly GetSummaryAction $getSummaryAction,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $defaultSourceId = $user->sources()
            ->where('is_default', true)
            ->value('id');

        $this->logger->info($this->formatLogMessage('request received'), [
            'user_id' => $user->id,
            'default_source_id' => $defaultSourceId,
        ]);

        $output = $this->getSummaryAction->execute(
            new GetSummaryInput($user->id, $defaultSourceId)
        );

        return response()->json($output->toArray());
    }
}
