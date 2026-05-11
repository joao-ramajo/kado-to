<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Action\Source\GetSourceDetailsAction;
use App\DTO\Source\GetSourceDetailsInput;
use App\Http\Controllers\Controller;
use App\Support\Logging\FormatsLogMessage;
use Illuminate\Support\Facades\Auth;
use Psr\Log\LoggerInterface;

class GetSourceDetailsController extends Controller
{
    use FormatsLogMessage;

    public function __construct(
        private readonly GetSourceDetailsAction $getSourceDetailsAction,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke()
    {
        $userId = Auth::id();
        $this->logger->info($this->formatLogMessage('request received'), [
            'user_id' => $userId,
        ]);

        $output = $this->getSourceDetailsAction->execute(
            new GetSourceDetailsInput($userId)
        );

        return response()->json($output->toArray());
    }
}
