<?php

namespace App\Http\Requests;

use App\Models\Screen;
use App\Support\DeviceId;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ScreenAcknowledgementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $screen = $this->route('screen');

        return $screen instanceof Screen
            && $this->user()?->can('update', $screen) === true
            && hash_equals($screen->device_id, DeviceId::current());
    }

    protected function failedAuthorization(): never
    {
        abort(404);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'presentationId' => ['required', 'integer'],
            'appliedVersion' => ['required', 'integer', 'min:0'],
            'drawnRevision' => ['nullable', 'string', 'max:32'],
        ];
    }
}
