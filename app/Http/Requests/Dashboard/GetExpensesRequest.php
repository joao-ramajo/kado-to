<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class GetExpensesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'in:all,paid,pending,overdue'],
            'query' => ['nullable', 'string'],
            'category_id' => [
                'nullable',
                'integer'
            ],
            'source_id' => [
                'nullable',
                'integer',
                Rule::exists('sources', 'id')->where(
                    fn ($query) => $query->where('user_id', Auth::id())
                ),
            ],
            'month' => ['nullable', 'integer', 'between:1,12'],
        ];
    }
}
