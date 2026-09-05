<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExportBookletPdfRequest extends FormRequest
{
    /**
     * A booklet runs longer than a single score, but not without limit — the
     * pages arrive as SVG in one request body.
     */
    public const MAX_PAGES = 120;

    public const MAX_PAGE_BYTES = 5_000_000;

    /**
     * Authorized by the route's model binding and the booklet policy, unlike the
     * single-score export, which is deliberately open to guests.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('booklet')) === true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pages' => ['required', 'array', 'min:1', 'max:'.self::MAX_PAGES],
            'pages.*' => ['required', 'string', 'max:'.self::MAX_PAGE_BYTES, 'regex:/^\s*<(\?xml|svg)/i'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pages.required' => __('There is nothing to export.'),
            'pages.max' => __('Too many pages to export at once.'),
            'pages.*.regex' => __('Each page must be a valid SVG document.'),
        ];
    }
}
