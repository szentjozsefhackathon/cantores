<?php

namespace App\Http\Requests;

use App\Models\Screen;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * What a screen is to be pointed at — and where on it the picture should land.
 *
 * `projectionId`'s absence means something different from its being null:
 * `projectionId` omitted is a request that says only "still here", while
 * `projectionId` null is the deliberate sentence that takes the deck off the
 * wall and ends the service.
 */
class ScreenStateRequest extends FormRequest
{
    /**
     * Ownership of the screen, asked here and narrowed to a 404 in the
     * controller — a screen somebody else is facing a room with is not a thing
     * this account may know exists.
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
            'projectionId' => ['sometimes', 'nullable', 'integer'],
            'fit' => ['sometimes', 'array'],
            'fit.scale' => ['sometimes', 'numeric'],
            'fit.x' => ['sometimes', 'numeric'],
            'fit.y' => ['sometimes', 'numeric'],
        ];
    }

    /**
     * Whether this request is asking the screen to show something else at all,
     * as against merely saying that the browser is still there.
     */
    public function pointsSomewhere(): bool
    {
        return $this->has('projectionId');
    }

    public function projectionId(): ?int
    {
        $id = $this->input('projectionId');

        return $id === null ? null : (int) $id;
    }

    /**
     * Whether this request is moving the picture on the wall.
     *
     * Its own question, and not part of pointing the screen anywhere: a cantor
     * lining the beamer up is not changing what the room is looking at, and a
     * remote nudging the picture mid-hymn must not so much as touch the deck.
     */
    public function adjustsFit(): bool
    {
        return $this->has('fit');
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
