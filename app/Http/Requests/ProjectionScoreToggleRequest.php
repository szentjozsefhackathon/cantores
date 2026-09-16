<?php

namespace App\Http\Requests;

use App\Models\Projection;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One score, or one of its files, to add to a deck or take out of it — the
 * remote's own way in to what the editor's plan pane already lets its owner do.
 */
class ProjectionScoreToggleRequest extends FormRequest
{
    /**
     * Ownership of the deck, asked here and narrowed to a 404 in the
     * controller — a deck somebody else is arranging is not a thing this
     * account may know exists.
     */
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
        return [
            'scoreId' => ['required', 'integer'],
            'assignmentId' => ['sometimes', 'nullable', 'integer'],
            'fileId' => ['sometimes', 'nullable', 'integer'],
            'addedMusicId' => ['sometimes', 'nullable', 'integer'],
        ];
    }

    public function scoreId(): int
    {
        return (int) $this->input('scoreId');
    }

    public function assignmentId(): ?int
    {
        $id = $this->input('assignmentId');

        return $id === null ? null : (int) $id;
    }

    /**
     * A music only this deck holds, which the toggle checks is really this
     * deck's own.
     */
    public function addedMusicId(): ?int
    {
        $id = $this->input('addedMusicId');

        return $id === null ? null : (int) $id;
    }

    public function fileId(): ?int
    {
        $id = $this->input('fileId');

        return $id === null ? null : (int) $id;
    }
}
