<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExportDiatarRequest;
use App\Models\MusicPlan;
use App\Services\Diatar\DiatarExportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DiatarExportController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        ExportDiatarRequest $request,
        MusicPlan $musicPlan,
        DiatarExportService $exporter,
    ): StreamedResponse {
        $download = $exporter->export($musicPlan, $request->validated());

        return response()->streamDownload(
            static function () use ($download): void {
                echo $download['contents'];
            },
            $download['filename'],
            ['Content-Type' => 'application/octet-stream; charset=UTF-8'],
        );
    }
}
