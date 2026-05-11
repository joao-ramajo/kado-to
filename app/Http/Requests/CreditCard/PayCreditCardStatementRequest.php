<?php

declare(strict_types=1);

namespace App\Http\Requests\CreditCard;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class PayCreditCardStatementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'payment_source_id' => ['required', 'integer', 'exists:sources,id'],
        ];
    }
}
