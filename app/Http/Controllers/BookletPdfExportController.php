<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExportBookletPdfRequest;
use App\Models\Booklet;
use App\Services\BookletImposer;
use App\Services\BookletScorePageInliner;
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
     * The one thing the server decides for itself is the imposition: the pages
     * arrive in reading order at their own size whatever is being printed, and
     * an imposed export nests them onto A4 sheets here, where the paper sizes
     * already live.
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
        BookletScorePageInliner $inliner,
        BookletImposer $imposer,
    ): Response {
        /** @var list<string> $pages */
        $pages = $request->validated('pages');

        // A vector file's systems arrive as placeholders; put the stored page
        // back behind each one, re-checking access as it does.
        $pages = $inliner->inline($pages, $booklet, $request->user());

        try {
            $pages = $imposer->impose($pages, $booklet, $request->imposition());

            $pdf = $converter->convert($pages);
        } catch (RuntimeException $e) {
            report($e);

            abort(502, __('Could not generate the PDF.'));
        }

        // The name says what came out: the paper the pages were engraved for,
        // and, when they were imposed, that too — the pages inside an imposed
        // PDF are shuffled and doubled up, so it is not the file anybody wants
        // to read on screen, and both files are downloaded from the same menu.
        $suffix = $request->imposition()->imposes()
            ? '.'.Str::slug($request->imposition()->value)
            : '';

        $filename = (Str::slug($booklet->title) ?: 'fuzet')
            .'.'.$booklet->page_size->value
            .$suffix
            .'.cantores.hu.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
