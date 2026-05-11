<?php

declare(strict_types=1);

use App\Models\Expense;
use App\Models\User;

test('usuário pode deletar uma despesa', function () {
    $user = User::factory()->create();
    $source = $user->sources()->first();
    $expense = Expense::factory()->create(['user_id' => $user->id, 'source_id' => $source->id]);

    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer $token")
        ->deleteJson(route('api.expenses.delete', ['id' => $expense->id]));

    $response->assertStatus(200);

    $this->assertDatabaseMissing('expenses', [
        'id' => $expense->id,
    ]);
});
