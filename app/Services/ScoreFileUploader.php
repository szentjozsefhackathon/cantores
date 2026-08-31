<?php

namespace App\Services;

use App\Enums\ScoreFileRenderStatus;
use App\Enums\ScoreFileRights;
use App\Jobs\RenderScoreFileJob;
use App\Models\Score;
use App\Models\ScoreFile;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Takes an uploaded sheet music file into the library.
 *
 * The file lands encrypted, its row is created, and the render job is queued.
 * A .mscz carries the thumbnail MuseScore saved with it, so an incipit exists
 * the moment the upload finishes rather than whenever the queue gets to it.
 *
 * A score holds as many files as its owner needs — typically the editable
 * source beside the PDFs cut for the paper the choir prints on — so an upload
 * is added to the score rather than taking the place of what is already there.
 */
class ScoreFileUploader
{
    public function __construct(
        private readonly ScoreFileStorage $storage,
        private readonly MuseScoreMetadata $metadata,
        private readonly ScoreFileIncipitCropper $cropper,
    ) {}

    public function store(Score $score, UploadedFile $upload, ScoreFileRights $rights, ?string $label = null): ScoreFile
    {
        $bytes = $this->read($upload);

        $scoreFile = $score->files()->create([
            'path' => '',
            'original_name' => $upload->getClientOriginalName(),
            'label' => $this->cleanLabel($label),
            'mime' => $upload->getClientMimeType(),
            'size_bytes' => strlen($bytes),
            'checksum' => hash('sha256', $bytes),
            'rights' => $rights,
            'render_status' => ScoreFileRenderStatus::Pending,
        ]);

        $this->writeSource($scoreFile, $upload, $bytes);

        RenderScoreFileJob::dispatch($scoreFile);

        return $scoreFile;
    }

    /**
     * Put new bytes behind an existing row, keeping its label, its rights and
     * the links that already point at it.
     */
    public function replace(ScoreFile $scoreFile, UploadedFile $upload): ScoreFile
    {
        $bytes = $this->read($upload);

        // Every artifact under the directory belongs to the bytes being
        // replaced: the old pages would otherwise outlive the file they came
        // from and be served alongside the new ones.
        $this->storage->deleteAll($scoreFile);

        $scoreFile->update([
            'original_name' => $upload->getClientOriginalName(),
            'mime' => $upload->getClientMimeType(),
            'size_bytes' => strlen($bytes),
            'checksum' => hash('sha256', $bytes),
            'render_status' => ScoreFileRenderStatus::Pending,
            'render_error' => null,
            'has_thumbnail' => false,
            'page_count' => null,
            'rendered_at' => null,
        ]);

        $this->writeSource($scoreFile, $upload, $bytes);

        RenderScoreFileJob::dispatch($scoreFile);

        return $scoreFile;
    }

    /**
     * @throws RuntimeException when the upload cannot be read
     */
    private function read(UploadedFile $upload): string
    {
        $bytes = @file_get_contents($upload->getRealPath());

        if ($bytes === false || $bytes === '') {
            throw new RuntimeException('The uploaded score file could not be read.');
        }

        return $bytes;
    }

    /**
     * Store the uploaded bytes and record where they went. The path carries the
     * id, so it can only be settled once the row exists.
     */
    private function writeSource(ScoreFile $scoreFile, UploadedFile $upload, string $bytes): void
    {
        $extension = $this->safeExtension($upload->getClientOriginalExtension());

        $path = $scoreFile->directory().'/source.'.$extension;
        $this->storage->put($path, $bytes);
        $scoreFile->update(['path' => $path]);

        if ($extension === 'mscz') {
            $this->storeEmbeddedThumbnail($scoreFile, $bytes);
        }
    }

    private function cleanLabel(?string $label): ?string
    {
        $clean = trim((string) $label);

        return $clean === '' ? null : $clean;
    }

    /**
     * What a .mscz can say about itself before MuseScore is involved, used to
     * prefill the editor while the file is still only staged.
     *
     * @return array{title: ?string, composer: ?string, lyricist: ?string, thumbnail: ?string}
     */
    public function inspect(UploadedFile $upload): array
    {
        $empty = ['title' => null, 'composer' => null, 'lyricist' => null, 'thumbnail' => null];

        if (strtolower($upload->getClientOriginalExtension()) !== 'mscz') {
            return $empty;
        }

        $bytes = @file_get_contents($upload->getRealPath());

        if ($bytes === false || $bytes === '') {
            return $empty;
        }

        try {
            return $this->metadata->read($bytes);
        } catch (\Throwable) {
            return $empty;
        }
    }

    /**
     * The extension reaches a storage path, so keep it to the characters an
     * extension can legitimately contain. Validation has already restricted the
     * upload to the renderable formats — this is the belt to those braces.
     */
    private function safeExtension(string $extension): string
    {
        $clean = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $extension) ?? '');

        return $clean === '' ? 'bin' : $clean;
    }

    public function delete(ScoreFile $scoreFile): void
    {
        $this->storage->deleteAll($scoreFile);
        $scoreFile->delete();
    }

    /**
     * Crop the thumbnail MuseScore embedded in the .mscz to the same shape the
     * rendered incipit will have, so the listing does not change proportions
     * when the render job replaces it with the sharper version.
     */
    private function storeEmbeddedThumbnail(ScoreFile $scoreFile, string $mscz): void
    {
        try {
            $thumbnail = $this->metadata->read($mscz)['thumbnail'];

            if ($thumbnail === null) {
                return;
            }

            $this->storage->put($scoreFile->thumbPath(), $this->cropper->crop($thumbnail));
            $scoreFile->update(['has_thumbnail' => true]);
        } catch (\Throwable) {
            // A preview is a nicety; the render job produces the real one.
        }
    }
}
