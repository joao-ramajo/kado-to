<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()
    ->json([
        'message' => 'Bem vindo ao Kado API',
        'option' => 'Para dúvidas, mande um email para joaoramajo744@gmail.com',
        'web' => 'Acesse a aplicação em: https://kado-tan.vercel.app/inicio'
    ]));
