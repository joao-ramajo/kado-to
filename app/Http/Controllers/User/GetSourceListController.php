<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Action\Source\GetSourceListAction;
use App\DTO\Source\GetSourceListInput;
use App\Http\Controllers\Controller;
use App\Support\Logging\FormatsLogMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Psr\Log\LoggerInterface;

class GetSourceListController extends Controller
{
    use FormatsLogMessage;

    public function __construct(
        private readonly GetSourceListAction $getSourceListAction,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(Request $request)
    {
        $userId = Auth::id();
        $this->logger->info($this->formatLogMessage('request received'), [
            'user_id' => $userId,
        ]);

        $output = $this->getSourceListAction->execute(
            new GetSourceListInput($userId)
        );

        return response()->json($output->toArray(), 200);
    }
}
