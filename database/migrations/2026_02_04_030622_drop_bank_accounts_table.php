<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove coluna de expenses
        Schema::table('expenses', function (Blueprint $table) {
            if (Schema::hasColumn('expenses', 'bank_account_id')) {
                $table->dropForeign(['bank_account_id']);
                $table->dropColumn('bank_account_id');
            }
        });

        // Drop tabela inteira
        Schema::dropIfExists('bank_accounts');
    }

    public function down(): void
    {
        // Recria tabela
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->nullable();
            $table->timestamps();
        });

        // Recria coluna
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('bank_account_id')
                ->nullable()
                ->constrained('bank_accounts')
                ->nullOnDelete();
        });
    }
};
