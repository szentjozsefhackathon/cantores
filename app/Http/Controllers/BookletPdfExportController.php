<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExportBookletPdfRequest;
use App\Models\Booklet;
use App\Services\SvgToPdfConverter;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use RuntimeException;

class BookletPdfExportController extends Controller
{
    /**
     * The pages arrive already engraved: the browser holds the four renderers, so
     * it is the browser that lays the booklet out, and the server's part is the
     * one thing it can do that a browser cannot — turn a stack of SVG documents
     * into a single properly sized PDF.
     *
     * No credit line is passed to the converter. It stamps one line on every
     * page, which is right for a single published score and wrong for a booklet
     * of many: the attributions are drawn into the flow beneath the scores they
     * belong to instead.
     */
    public function __invoke(
        ExportBookletPdfRequest $request,
        Booklet $booklet,
        SvgToPdfConverter $converter,
    ): Response {
        /** @var list<string> $pages */
        $pages = $request->validated('pages');

        try {
            $pdf = $converter->convert($pages);
        } catch (RuntimeException $e) {
            report($e);

            abort(502, __('Could not generate the PDF.'));
        }

        $filename = (Str::slug($booklet->title) ?: 'fuzet').'.cantores.hu.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
