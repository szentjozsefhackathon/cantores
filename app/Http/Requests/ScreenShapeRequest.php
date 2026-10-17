<?php

namespace App\Http\Requests;

use App\Models\Screen;
use App\Support\DeviceId;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The shape of the window a wall is drawing into.
 *
 * Said by the wall itself and by nothing else: the phone may move the picture,
 * but only the browser plugged into the projector knows what shape it is.
 */
class ScreenShapeRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'shape' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
