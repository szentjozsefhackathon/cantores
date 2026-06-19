<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExportScorePdfRequest;
use App\Services\SvgToPdfConverter;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use RuntimeException;

class ScorePdfExportController extends Controller
{
    public function __invoke(ExportScorePdfRequest $request, SvgToPdfConverter $converter): Response
    {
        /** @var list<string> $pages */
        $pages = $request->validated('pages');

        try {
            $pdf = $converter->convert($pages);
        } catch (RuntimeException $e) {
            report($e);

            abort(502, __('Could not generate the PDF.'));
        }

        $filename = (Str::slug((string) $request->validated('title')) ?: 'score').'.cantores.hu.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
