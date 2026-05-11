<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CreateSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'in:cash_like,credit_card'],
            'color' => ['required', 'string', 'size:7'],
            'allow_negative' => ['boolean'],
            'credit_limit' => ['nullable', 'integer', 'min:1', 'required_if:type,credit_card'],
            'statement_closing_day' => ['nullable', 'integer', 'between:1,31', 'required_if:type,credit_card'],
            'statement_due_day' => ['nullable', 'integer', 'between:1,31', 'required_if:type,credit_card', 'gt:statement_closing_day'],
        ];
    }
}
