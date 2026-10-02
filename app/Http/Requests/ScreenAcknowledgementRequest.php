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
            'drawnLayout' => ['nullable', 'array', 'max:500'],
            'drawnLayout.*' => ['array', 'max:200'],
            'drawnLayout.*.*' => ['array', 'min:1', 'max:200'],
            'drawnLayout.*.*.*' => ['integer', 'min:0'],
        ];
    }

    /**
     * Where the wall cut each row into slides, keyed by row: one list per page
     * of the row, holding the index each of its slides starts at.
     *
     * Null when the wall said nothing about it — a tab opened on the previous
     * bundle — which leaves the phone cutting the deck for itself.
     *
     * @return array<int, list<list<int>>>|null
     */
    public function drawnLayout(): ?array
    {
        $layout = $this->input('drawnLayout');

        if (! is_array($layout)) {
            return null;
        }

        return collect($layout)
            ->filter(fn (mixed $pages, int|string $entryId): bool => ctype_digit((string) $entryId))
            ->mapWithKeys(fn (array $pages, int|string $entryId): array => [
                (int) $entryId => array_map(
                    fn (array $starts): array => array_map(intval(...), array_values($starts)),
                    array_values($pages),
                ),
            ])
            ->all();
    }
}
