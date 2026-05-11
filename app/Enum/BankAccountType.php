<?php

declare(strict_types=1);

namespace App\Enum;

enum BankAccountType: string
{
    case Checking = 'checking';
    case Savings = 'savings';
    case Credit = 'credit';

    public function label(): string
    {
        return match ($this) {
            self::Checking => 'Conta Corrente',
            self::Savings => 'Conta Poupança',
            self::Credit => 'Cartão de Crédito',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Checking => 'Conta usada no dia a dia para pagamentos e recebimentos',
            self::Savings => 'Conta destinada a guardar dinheiro e rendimentos',
            self::Credit => 'Cartão de crédito com fatura e limite',
        };
    }
}
