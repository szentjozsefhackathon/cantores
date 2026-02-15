<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMusicPlanSlotRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(?int $slotId = null): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('music_plan_slots', 'name')
                    ->whereNull('deleted_at')
                    ->ignore($slotId),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'priority' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => __('A slot name is required.'),
            'name.unique' => __('A slot with this name already exists.'),
            'description.max' => __('Description cannot exceed 1000 characters.'),
        ];
    }
}
