<?php

declare(strict_types=1);

namespace App\Action\Auth;

use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\Hash;

class LoginAction
{
    public function execute(array $credentials): array
    {
        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw new DomainException('Credenciais inválidas.');
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return ['token' => $token, 'name' => $user->name];
    }
}
