<?php

namespace App\Http\Requests;

use App\Models\MusicPlan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ExportDiatarRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $musicPlan = $this->route('musicPlan');

        return $musicPlan instanceof MusicPlan
            && ! $musicPlan->is_private
            && ($this->user()?->can('update', $musicPlan) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'catalog_sync_run_id' => ['required', 'integer'],
            'catalog_revision' => ['required', 'string', 'max:64'],
            'rows' => ['required', 'array'],
            'rows.*' => ['required', 'array:assignment_id,song_id,omitted,slide_ids'],
            'rows.*.assignment_id' => ['required', 'integer', 'distinct'],
            'rows.*.song_id' => ['nullable', 'integer'],
            'rows.*.omitted' => ['required', 'boolean'],
            'rows.*.slide_ids' => ['present', 'array'],
            'rows.*.slide_ids.*' => ['integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'catalog_sync_run_id.required' => __('The catalogue synchronization is missing. Reopen the Diatár export dialog.'),
            'catalog_revision.required' => __('The catalogue revision is missing. Reopen the Diatár export dialog.'),
            'rows.required' => __('The Diatár export selection is missing.'),
            'rows.*.assignment_id.distinct' => __('Each plan assignment may appear only once.'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $rows = collect($this->input('rows', []))->map(function ($row): array {
            $row = is_array($row) ? $row : [];
            $slideIds = $row['slide_ids'] ?? [];
            $row['slide_ids'] = collect(is_array($slideIds) ? $slideIds : [$slideIds])
                ->filter(fn ($id): bool => $id !== null && $id !== '')
                ->values()
                ->all();

            return $row;
        })->all();

        $this->merge(['rows' => $rows]);
    }
}
