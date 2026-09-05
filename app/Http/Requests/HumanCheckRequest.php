<?php

namespace App\Http\Requests;

use App\Rules\TurnstileWithDummy;
use Illuminate\Foundation\Http\FormRequest;

class HumanCheckRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Anyone may answer the challenge — that is the whole point of it.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cf-turnstile-response' => ['required', new TurnstileWithDummy],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cf-turnstile-response.required' => __('Please complete the check to show you are not a robot.'),
        ];
    }
}
