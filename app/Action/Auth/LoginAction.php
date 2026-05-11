<?php

declare(strict_types=1);

namespace App\Action\Auth;

use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\Hash;

class LoginAction
{
    /**
     * @param array{email: string, password: string, remember?: bool|null} $credentials
     * @return array{name: string, token: string}
     */
    public function execute(array $credentials): array
    {
        $user = User::query()->where('email', $credentials['email'])->first();

        throw_if(! $user || ! Hash::check($credentials['password'], $user->password), DomainException::class, 'Credenciais inválidas.');

        $token = $user->createToken('auth_token')->plainTextToken;

        return ['token' => $token, 'name' => $user->name];
    }
}
