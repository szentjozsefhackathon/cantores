<?php

namespace App\Http\Requests;

/**
 * What the cantor typed into the remote's music search.
 */
class ProjectionMusicSearchRequest extends ProjectionDeckEditRequest
{
    /**
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'q.max' => __('The search is too long.'),
        ];
    }

    public function term(): string
    {
        return trim((string) $this->input('q', ''));
    }
}
