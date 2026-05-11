<?php

declare(strict_types=1);

namespace App\Providers;

use App\Action\Auth\WebLoginAction;
use App\Action\Auth\WebLogoutAction;
use App\Action\Auth\WebRegisterAction;
use App\Action\Category\CreateCategoryAction;
use App\Action\Category\GetCategoryListAction;
use App\Action\Dashboard\GetExpensesAction;
use App\Action\Dashboard\GetSummaryAction;
use App\Action\Expense\MarkExpenseAsPaidAction;
use App\Action\ImportCsvData;
use App\Action\Source\CreateSourceAction;
use App\Action\Source\GetSourceDetailsAction;
use App\Action\Source\GetSourceListAction;
use App\Action\Xlsx\GenerateExpensesXlsxAction;
use App\Domain\Uuid;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Dashboard\GenerateExpensesXlsx;
use App\Http\Controllers\Dashboard\GetExpensesController;
use App\Http\Controllers\Dashboard\GetSummaryController;
use App\Http\Controllers\Expense\CreateCategoryController;
use App\Http\Controllers\Expense\GetCategoryListController;
use App\Http\Controllers\Expense\MarkExpenseAsPaidController;
use App\Http\Controllers\User\CreateSourceController;
use App\Http\Controllers\User\GetSourceDetailsController;
use App\Http\Controllers\User\GetSourceListController;
use App\Jobs\User\SendWelcomeMailJob;
use App\Strategy\CsvExportStrategy;
use Illuminate\Log\LogManager;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->bindChannelLogger('auth', [
            AuthController::class,
            WebLoginAction::class,
            WebRegisterAction::class,
            WebLogoutAction::class,
        ]);

        $this->bindChannelLogger('dashboard', [
            GetSummaryController::class,
            GetExpensesController::class,
            GetSummaryAction::class,
            GetExpensesAction::class,
        ]);

        $this->bindChannelLogger('source', [
            CreateSourceController::class,
            GetSourceListController::class,
            GetSourceDetailsController::class,
            CreateSourceAction::class,
            GetSourceListAction::class,
            GetSourceDetailsAction::class,
        ]);

        $this->bindChannelLogger('category', [
            CreateCategoryController::class,
            GetCategoryListController::class,
            CreateCategoryAction::class,
            GetCategoryListAction::class,
        ]);

        $this->bindChannelLogger('expense', [
            MarkExpenseAsPaidController::class,
            MarkExpenseAsPaidAction::class,
        ]);

        $this->bindChannelLogger('xlsx', [
            GenerateExpensesXlsx::class,
            GenerateExpensesXlsxAction::class,
        ]);

        $this->bindChannelLogger('import', [
            ImportCsvData::class,
        ]);

        $this->bindChannelLogger('export', [
            CsvExportStrategy::class,
        ]);

        $this->bindChannelLogger('mail', [
            SendWelcomeMailJob::class,
        ]);
    }

    public function boot(): void
    {
        Route::bind('uuid', fn(string $value): Uuid => new Uuid($value));
    }

    /** @param list<class-string> $classes */
    private function bindChannelLogger(string $channel, array $classes): void
    {
        $this->app
            ->when($classes)
            ->needs(LoggerInterface::class)
            ->give(fn($app) => $app->make(LogManager::class)->channel($channel));
    }
}
