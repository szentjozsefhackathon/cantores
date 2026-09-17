<?php

namespace App\Http\Requests;

use App\Models\Screen;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Where on one screen the picture should land.
 *
 * Still addressed to a device, when nothing else is: every projector is hung
 * differently, so the fit is a fact about the room and not about the show.
 */
class ScreenFitRequest extends FormRequest
{
    /**
     * Ownership of the screen, narrowed to a 404 — a screen somebody else is
     * facing a room with is not a thing this account may know exists.
     */
    public function authorize(): bool
    {
        $screen = $this->route('screen');

        return $screen instanceof Screen
            && $this->user()?->can('update', $screen) === true;
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
            'fit' => ['required', 'array'],
            'fit.scale' => ['sometimes', 'numeric'],
            'fit.x' => ['sometimes', 'numeric'],
            'fit.y' => ['sometimes', 'numeric'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fit.required' => __('Say where the picture should land.'),
            'fit.*.numeric' => __('A fit is made of numbers.'),
        ];
    }

    /**
     * The nudge itself. Bounds are the screen's own business; what is left out
     * here is simply left where it was.
     *
     * @return array{scale?: float, x?: float, y?: float}
     */
    public function fit(): array
    {
        $fit = [];

        foreach (['scale', 'x', 'y'] as $field) {
            if ($this->has("fit.{$field}")) {
                $fit[$field] = (float) $this->input("fit.{$field}");
            }
        }

        return $fit;
    }
}
