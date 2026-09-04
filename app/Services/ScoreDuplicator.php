<?php

namespace App\Services;

use App\Enums\ScoreFileRenderStatus;
use App\Jobs\RenderScoreFileJob;
use App\Models\Score;
use App\Models\ScoreFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Copies a score into a new one, so a variation starts from a sibling that is
 * already set up rather than from an empty editor.
 *
 * Everything the owner arranged comes along — the music it belongs to, the
 * source, the render settings, the links, the folders and the uploaded files —
 * but nothing that exposes it: the copy is not previewable to guests, carries
 * no publication and inherits no share link. Those are decisions made about one
 * score, not properties of its contents.
 */
class ScoreDuplicator
{
    /** What the variation_name column holds. */
    private const NAME_LIMIT = 120;

    public function __construct(private readonly ScoreFileStorage $storage) {}

    public function duplicate(Score $score): Score
    {
        $copy = DB::transaction(function () use ($score): Score {
            $copy = Score::create([
                'user_id' => $score->user_id,
                'music_id' => $score->music_id,
                'title' => $score->title,
                'variation_name' => $this->copiedVariationName($score),
                'format' => $score->format,
                'content' => $score->content,
                'settings' => $score->settings,
                'public_preview' => false,
            ]);

            foreach ($score->urls()->orderBy('id')->get() as $url) {
                $copy->urls()->create([
                    'url' => $url->url,
                    'label' => $url->label,
                    'comment' => $url->comment,
                ]);
            }

            $copy->folders()->sync($score->folders()->pluck('folders.id')->all());

            return $copy;
        });

        foreach ($score->orderedFiles() as $file) {
            $this->duplicateFile($copy, $file);
        }

        $this->duplicateIncipit($score, $copy);

        return $copy;
    }

    /**
     * A name that tells the copy apart from what it was copied from, since the
     * two would otherwise sit side by side in the variations list under the
     * same label. The owner renames it the moment they know what it is.
     */
    private function copiedVariationName(Score $score): string
    {
        return Str::limit(__(':name (copy)', ['name' => $score->variationLabel()]), self::NAME_LIMIT, '');
    }

    /**
     * Copy a file row and the artifacts behind it.
     *
     * The bytes are copied as they lie — still encrypted — so nothing is
     * decrypted to duplicate a file, and the copy's pages are readable at once
     * instead of waiting on the renderer. A file the renderer has not finished
     * with has no artifacts worth copying, so the copy is queued afresh.
     */
    private function duplicateFile(Score $copy, ScoreFile $file): void
    {
        $isRendered = $file->render_status->isFinal();

        $duplicate = $copy->files()->create([
            'path' => '',
            'original_name' => $file->original_name,
            'label' => $file->label,
            'mime' => $file->mime,
            'size_bytes' => $file->size_bytes,
            'checksum' => $file->checksum,
            'rights' => $file->rights,
            'is_published' => false,
            'render_status' => $isRendered ? $file->render_status : ScoreFileRenderStatus::Pending,
            'render_error' => $isRendered ? $file->render_error : null,
            'has_thumbnail' => $isRendered && $file->has_thumbnail,
            'page_count' => $isRendered ? $file->page_count : null,
            'rendered_at' => $isRendered ? $file->rendered_at : null,
        ]);

        $directory = $file->directory();

        foreach ($this->storage->disk()->files($directory) as $path) {
            $this->storage->disk()->copy($path, $duplicate->directory().'/'.basename($path));
        }

        $duplicate->update([
            'path' => $duplicate->directory().'/'.basename($file->path),
        ]);

        if (! $isRendered) {
            RenderScoreFileJob::dispatch($duplicate);
        }
    }

    /**
     * The incipit the browser drew for the original stands for the copy too:
     * the two hold the same notes until the owner edits one of them.
     */
    private function duplicateIncipit(Score $score, Score $copy): void
    {
        if (Storage::exists($score->incipit_path)) {
            Storage::copy($score->incipit_path, $copy->incipit_path);
        }
    }
}
