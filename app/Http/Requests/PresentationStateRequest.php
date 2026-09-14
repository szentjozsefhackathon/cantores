<?php

namespace App\Http\Requests;

use App\Models\Presentation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Where the service has got to, as one of its devices reports it.
 *
 * Every field is optional, because the two things that write here write for
 * different reasons: the presenter reports a keystroke, and then goes on
 * reporting a heartbeat that carries nothing but the revision it has finished
 * drawing. A request that says nothing is a request that says "still here".
 */
class PresentationStateRequest extends FormRequest
{
    /**
     * Ownership of the row, checked in the controller so that a refusal can be
     * a 404 rather than a 403. Passing here would be the wrong answer, so the
     * same question is asked and the controller narrows the reply.
     */
    public function authorize(): bool
    {
        $presentation = $this->route('presentation');

        return $presentation instanceof Presentation
            && $this->user()?->can('update', $presentation) === true;
    }

    /**
     * A service somebody else is running is not a thing this account may know
     * exists, so the refusal is a 404 rather than the 403 a failed authorize
     * would give.
     */
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
            'entryId' => ['sometimes', 'nullable', 'integer'],
            'slideIndex' => ['sometimes', 'integer', 'min:0'],
            'blanked' => ['sometimes', 'boolean'],
            'drawnRevision' => ['sometimes', 'nullable', 'string', 'max:32'],
            'reveals' => ['sometimes', 'nullable', 'array'],
            'reveals.*' => ['array'],
            'reveals.*.*' => ['integer', 'min:0'],
            'ended' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Just the part of the state that moves the service, in the vocabulary
     * Presentation::applyState speaks.
     *
     * @return array{entryId?: int|null, slideIndex?: int, blanked?: bool, reveals?: array<int, list<int>>}
     */
    public function state(): array
    {
        $state = [];

        foreach (['entryId', 'slideIndex', 'blanked'] as $field) {
            if ($this->has($field)) {
                $state[$field] = $this->input($field);
            }
        }

        if ($this->has('reveals')) {
            $state['reveals'] = collect($this->input('reveals') ?? [])
                ->mapWithKeys(fn (array $slides, $entryId): array => [
                    (int) $entryId => array_values(array_unique(array_map(intval(...), $slides))),
                ])
                ->filter(fn (array $slides): bool => $slides !== [])
                ->all();
        }

        return $state;
    }
}
