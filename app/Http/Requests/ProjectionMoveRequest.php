<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/**
 * One node of a deck to move past its neighbour, from the remote.
 *
 * Only the shape of the request is checked here. Whether the move is allowed at
 * all is PlanOutline's answer, the same one the editors get.
 */
class ProjectionMoveRequest extends ProjectionDeckEditRequest
{
    /**
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', 'string', Rule::in(['entry', 'slot', 'music', 'added'])],
            'id' => ['required', 'integer'],
            'direction' => ['required', 'integer', Rule::in([-1, 1])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'kind.in' => __('That cannot be moved.'),
            'direction.in' => __('A move is one step up or down.'),
        ];
    }

    public function kind(): string
    {
        return (string) $this->input('kind');
    }

    public function nodeId(): int
    {
        return (int) $this->input('id');
    }

    public function direction(): int
    {
        return (int) $this->input('direction');
    }
}
