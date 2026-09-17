<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Which deck to put up as the show — or, with null, to take the show down.
 *
 * `projectionId` has to be said either way: a request that names nothing is not
 * a way to end a service by accident.
 */
class ShowStateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'projectionId' => ['present', 'nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'projectionId.present' => __('Say which deck to put up, or null to take it down.'),
            'projectionId.integer' => __('A deck is named by its number.'),
        ];
    }

    public function projectionId(): ?int
    {
        $id = $this->input('projectionId');

        return $id === null ? null : (int) $id;
    }
}
