<?php

declare(strict_types=1);

namespace App\Action\Auth;

use App\Events\UserRegistered;
use App\Models\Source;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RegisterUserAction
{
    /**
     * @param array{name: string, email: string, password: string, terms?: bool} $data
     * @return array{name: string, token: string}
     */
    public function execute(array $data): array
    {
        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        event(new UserRegistered($user->name, $user->email));

        Source::query()->create([
            'user_id' => $user->id,
            'name' => 'Carteira principal',
            'type' => Source::TYPE_CASH_LIKE,
            'color' => '#34c38f',
            'is_default' => true,
        ]);

        return ['name' => $user->name, 'token' => $token];
    }
}
