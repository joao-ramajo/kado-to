<?php

declare(strict_types=1);

namespace App\Http\Requests\Expense;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'amount' => 'required|integer|min:0',
            'type' => 'required|string',
            'status' => 'required',
            'category_id' => 'nullable|exists:categories,id',
            'source_id' => 'sometimes|exists:sources,id',
            'purchase_date' => [
                'nullable',
                'date_format:Y-m-d',
                'date',
                'before_or_equal:today',
            ],
            'payment_date' => [
                'nullable',
                'date_format:Y-m-d',
                'date',
                'before_or_equal:today',
            ],
        ];
    }
}
