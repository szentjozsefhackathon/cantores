<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMusicPlanTemplateSlotRequest extends FormRequest
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
    public function rules(): array
    {
        return [
            'slot_id' => ['required', 'exists:music_plan_slots,id'],
            'sequence' => ['required', 'integer', 'min:1'],
            'is_included_by_default' => ['boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'slot_id.required' => __('A slot must be selected.'),
            'slot_id.exists' => __('The selected slot does not exist.'),
            'sequence.required' => __('A sequence number is required.'),
            'sequence.min' => __('Sequence must be at least 1.'),
        ];
    }
}
