<?php

namespace App\Http\Requests\Reminders;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ReminderCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $color = $this->input('color');

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'color' => is_string($color) && $color !== '' ? Str::upper(trim($color)) : null,
        ]);
    }

    public function rules(): array
    {
        $categoryId = $this->route('reminder_category');

        return [
            'name' => [
                'required',
                'string',   
                'max:50',
                Rule::unique('reminder_categories', 'name')
                    ->where('user_id', $this->user()->id)
                    ->ignore($categoryId),
            ],
            'color' => [
                'nullable',
                'regex:/^#[0-9A-F]{6}$/',
                Rule::unique('reminder_categories', 'color')
                    ->where('user_id', $this->user()->id)
                    ->ignore($categoryId),
            ],
        ];
    }
}
