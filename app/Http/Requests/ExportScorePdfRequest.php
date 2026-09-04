<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExportScorePdfRequest extends FormRequest
{
    /**
     * Maximum number of pages (SVG inputs) accepted in a single request.
     */
    public const MAX_PAGES = 50;

    /**
     * Maximum length, in bytes, of a single SVG page payload.
     */
    public const MAX_PAGE_BYTES = 5_000_000;

    /**
     * Determine if the user is authorized to make this request.
     *
     * The endpoint is intentionally public so guests can export from the score
     * editor; abuse is mitigated by CSRF protection and rate limiting on the
     * route rather than authentication.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'format' => ['required', 'string', 'in:abc,gabc,aretino'],
            'title' => ['nullable', 'string', 'max:255'],
            // Which score this came from, so a published one can be stamped
            // with its credit. Optional: the editor exports without a score.
            'score_id' => ['nullable', 'integer'],
            'pages' => ['required', 'array', 'min:1', 'max:'.self::MAX_PAGES],
            'pages.*' => ['required', 'string', 'max:'.self::MAX_PAGE_BYTES, 'regex:/^\s*<(\?xml|svg)/i'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'format.in' => __('PDF export is only available for ABC, GABC and Aretino scores.'),
            'pages.required' => __('There is nothing to export.'),
            'pages.max' => __('Too many pages to export at once.'),
            'pages.*.regex' => __('Each page must be a valid SVG document.'),
        ];
    }
}
