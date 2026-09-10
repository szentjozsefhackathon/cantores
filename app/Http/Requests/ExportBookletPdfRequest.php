<?php

namespace App\Http\Requests;

use App\Enums\BookletImposition;
use App\Models\Booklet;
use App\Support\ImpositionLayout;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'imposition' => ['nullable', Rule::enum(BookletImposition::class)],
        ];
    }

    /**
     * How the pages are to be arranged on the paper. Nothing said means one page
     * per sheet, which is what every export was before there was a choice.
     */
    public function imposition(): BookletImposition
    {
        $value = $this->input('imposition');

        return is_string($value)
            ? BookletImposition::tryFrom($value) ?? BookletImposition::Full
            : BookletImposition::Full;
    }

    /**
     * A booklet already laid out at the size of the paper cannot be imposed onto
     * it, and the editor greys those choices out — so a request asking for one
     * anyway is refused rather than quietly answered with a full-page PDF.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->imposition()->imposes()) {
                return;
            }

            $booklet = $this->route('booklet');

            if ($booklet instanceof Booklet && ImpositionLayout::for($booklet) === null) {
                $validator->errors()->add(
                    'imposition',
                    __('These pages are already the size of the paper, so there is nothing to impose.'),
                );
            }
        });
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
