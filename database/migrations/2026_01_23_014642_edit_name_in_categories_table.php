<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // Remove a constraint única antiga de 'name'
            $table->dropUnique('categories_name_unique');

            // Cria uma nova unique combinando 'name' + 'user_id'
            $table->unique(['name', 'user_id']);

        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // Remove a nova unique combinada
            $table->dropUnique(['name', 'user_id']);

            // Restaura a unique global antiga
            $table->unique('name');
        });
    }
};
