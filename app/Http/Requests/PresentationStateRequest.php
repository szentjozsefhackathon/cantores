<?php

namespace App\Http\Requests;

use App\Models\Presentation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Where the service has got to, as one of its devices reports it.
 *
 * New clients send an ordered command. The flat fields remain accepted for one
 * deployment window so a presenter tab opened before a release keeps working.
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
            'sourceId' => ['required_with:sequence,changes', 'uuid'],
            'sequence' => ['required_with:sourceId,changes', 'integer', 'min:1'],
            'changes' => ['required_with:sourceId,sequence', 'array'],
            'changes.entryId' => ['sometimes', 'nullable', 'integer'],
            'changes.slideIndex' => ['sometimes', 'integer', 'min:0'],
            'changes.blanked' => ['sometimes', 'boolean'],
            'changes.splash' => ['sometimes', 'string', Rule::in(array_keys(Presentation::SPLASH_ORDER))],
            'changes.reveals' => ['sometimes', 'nullable', 'array'],
            'changes.reveals.*' => ['array'],
            'changes.reveals.*.*' => ['integer', 'min:0'],

            // Compatibility with tabs opened on the previous bundle.
            'entryId' => ['sometimes', 'nullable', 'integer'],
            'slideIndex' => ['sometimes', 'integer', 'min:0'],
            'blanked' => ['sometimes', 'boolean'],
            'splash' => ['sometimes', 'string', Rule::in(array_keys(Presentation::SPLASH_ORDER))],
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
     * @return array{entryId?: int|null, slideIndex?: int, blanked?: bool, splash?: string, reveals?: array<int, list<int>>}
     */
    public function state(): array
    {
        $prefix = $this->has('changes') ? 'changes.' : '';
        $state = [];

        foreach (['entryId', 'slideIndex', 'blanked', 'splash'] as $field) {
            if ($this->has($prefix.$field)) {
                $state[$field] = $this->input($prefix.$field);
            }
        }

        if ($this->has($prefix.'reveals')) {
            $state['reveals'] = collect($this->input($prefix.'reveals') ?? [])
                ->mapWithKeys(fn (array $slides, $entryId): array => [
                    (int) $entryId => array_values(array_unique(array_map(intval(...), $slides))),
                ])
                ->filter(fn (array $slides): bool => $slides !== [])
                ->all();
        }

        return $state;
    }

    public function isCommand(): bool
    {
        return $this->has('sourceId') && $this->has('sequence') && $this->has('changes');
    }
}
