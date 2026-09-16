<?php

namespace App\Http\Requests;

use App\Models\Projection;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A change to a deck made from the remote, by the deck's owner.
 *
 * Ownership is asked here and narrowed to a 404, as at the score toggle — a deck
 * somebody else is arranging is not a thing this account may know exists. The
 * remote's other writes extend this with what they carry.
 */
class ProjectionDeckEditRequest extends FormRequest
{
    public function authorize(): bool
    {
        $projection = $this->route('projection');

        return $projection instanceof Projection
            && $this->user()?->can('update', $projection) === true;
    }

    protected function failedAuthorization(): never
    {
        abort(404);
    }

    /**
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }

    public function projection(): Projection
    {
        /** @var Projection */
        return $this->route('projection');
    }
}
