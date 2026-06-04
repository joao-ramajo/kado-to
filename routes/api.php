<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\CreditCard\PayCreditCardStatementController;
use App\Http\Controllers\CreditCard\UndoPayCreditCardStatementController;
use App\Http\Controllers\Dashboard\GenerateExpenseCsv;
use App\Http\Controllers\Dashboard\GenerateExpensesXlsx;
use App\Http\Controllers\Dashboard\GetExpensesController;
use App\Http\Controllers\Dashboard\GetSummaryController;
use App\Http\Controllers\Expense\CreateCategoryController;
use App\Http\Controllers\Expense\CreateExpenseController;
use App\Http\Controllers\Expense\DeleteExpenseController;
use App\Http\Controllers\Expense\GetCategoryListController;
use App\Http\Controllers\Expense\ImportExpenseCsvController;
use App\Http\Controllers\Expense\MarkExpenseAsPaidController;
use App\Http\Controllers\Expense\UpdateCategoryController;
use App\Http\Controllers\Expense\UpdateExpenseController;
use App\Http\Controllers\User\CreateSourceController;
use App\Http\Controllers\User\DeleteSourceController;
use App\Http\Controllers\User\GetSourceDetailsController;
use App\Http\Controllers\User\GetSourceListController;
use App\Http\Controllers\User\UpdateSourceController;
use Illuminate\Support\Facades\Route;

Route::post('/register', RegisterController::class)->name('api.register');
Route::post('/login', LoginController::class)->name('api.login');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/users/sources', GetSourceListController::class)
        ->name('api.users.sources');

    Route::prefix('sources')->group(function (): void {
        Route::get('/', GetSourceDetailsController::class)
            ->name('api.sources.details');
        Route::post('/', CreateSourceController::class)
            ->name('api.sources.create');
        Route::delete('/{id}', DeleteSourceController::class)
            ->name('api.sources.delete');
        Route::put('/{id}', UpdateSourceController::class)
            ->name('api.sources.update');
    });

    Route::prefix('credit-cards/statements')->group(function (): void {
        Route::post('/{statementId}/pay', PayCreditCardStatementController::class)
            ->name('api.credit-cards.statements.pay');
        Route::post('/{statementId}/undo-pay', UndoPayCreditCardStatementController::class)
            ->name('api.credit-cards.statements.undo-pay');
    });

    Route::prefix('expenses')->group(function (): void {
        Route::post('/', CreateExpenseController::class)
            ->name('api.expenses.create');
        Route::put('/{id}', UpdateExpenseController::class)
            ->name('api.expenses.update');
        Route::post('/{id}/mark-as-paid', MarkExpenseAsPaidController::class)
            ->name('api.expenses.mark-as-paid');
        Route::delete('/{id}', DeleteExpenseController::class)
            ->name('api.expenses.delete');
    });

    Route::prefix('categories')->group(function (): void {
        Route::get('/', GetCategoryListController::class)
            ->name('api.categories.list');
        Route::post('/', CreateCategoryController::class)
            ->name('api.categories.create');
        Route::put('/{id}', UpdateCategoryController::class)
            ->name('api.categories.update');
    });

    Route::prefix('dashboard')->group(function (): void {
        Route::get('/summary', GetSummaryController::class)
            ->name('api.get-summary');
        Route::get('/expenses', GetExpensesController::class)
            ->name('api.get-expenses');
        Route::get('/spreadsheet/csv/export', GenerateExpenseCsv::class)
            ->name('api.csv.export');
        Route::post('/spreadsheet/csv/import', ImportExpenseCsvController::class)
            ->name('api.csv.import');
        Route::get('/spreadsheet/xlsx/export', GenerateExpensesXlsx::class)
            ->name('api.xlsx.export');
    });
});
