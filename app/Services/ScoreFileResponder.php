<?php

namespace App\Services;

use App\Models\ScoreFile;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves the encrypted artifacts of an uploaded score file over HTTP.
 *
 * Storage::response() cannot stream a ciphertext blob, so these bodies are
 * built in memory. What survives from the streamed version is the caching:
 * the ETag comes from the stored plaintext checksum and Last-Modified from the
 * row, so the 304 is decided before anything is decrypted and a repeat view
 * costs no crypto at all. Page images run 100–300 KB; the whole-file download
 * is bounded by the 25 MB upload cap.
 *
 * Private and public responses cache very differently. A private artifact has
 * a stable URL whose bytes only change on re-upload, so it is immutable for a
 * year. A public one has to be revocable: a score can be taken down after a
 * rightholder complains, and a year-long immutable response would keep serving
 * it from intermediaries long after it left the site. Public responses
 * therefore get a short max-age and must revalidate — which costs nothing,
 * because the checksum ETag still answers the revalidation without decrypting.
 */
class ScoreFileResponder
{
    /**
     * How long a public artifact may be reused before it must be revalidated.
     * Bounds how long a takedown can be outrun by a cache.
     */
    public const PUBLIC_MAX_AGE = 600;

    /**
     * A private artifact's URL only ever names one set of bytes.
     */
    public const PRIVATE_MAX_AGE = 31536000;

    public function __construct(
        private readonly ScoreFileStorage $storage,
    ) {}

    /**
     * A rendered page image.
     */
    public function page(ScoreFile $scoreFile, int $page, bool $public): Response
    {
        return $this->respond(
            $scoreFile,
            $scoreFile->pagePath($page),
            "page-{$page}",
            'image/png',
            $public,
        );
    }

    /**
     * The incipit crop, which stands in for the shared plaintext incipits/{id}.png
     * so a corner of a copyrighted scan is not left in the clear beside them.
     */
    public function thumbnail(ScoreFile $scoreFile, bool $public): Response
    {
        return $this->respond(
            $scoreFile,
            $scoreFile->thumbPath(),
            'thumb',
            'image/png',
            $public,
        );
    }

    /**
     * The original uploaded file, as an attachment under its own name.
     */
    public function download(ScoreFile $scoreFile, bool $public = false): Response
    {
        $response = $this->respond(
            $scoreFile,
            $scoreFile->path,
            'source',
            $scoreFile->mime ?: 'application/octet-stream',
            $public,
        );

        if ($public) {
            // Rank the landing page that carries the attribution, not an
            // orphaned file served without it.
            $response->headers->set('X-Robots-Tag', 'noindex');
        }

        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $scoreFile->original_name,
            $this->asciiFallbackName($scoreFile),
        ));

        return $response;
    }

    private function respond(ScoreFile $scoreFile, string $path, string $artifact, string $contentType, bool $public): Response
    {
        abort_unless($this->storage->exists($path), 404);

        $response = new Response;
        $response->headers->set('Content-Type', $contentType);
        $response->setEtag(substr(hash('sha256', $scoreFile->checksum.'|'.$artifact), 0, 32));
        $response->setLastModified(
            ($scoreFile->rendered_at ?? $scoreFile->updated_at ?? now())->toDateTime()
        );
        if ($public) {
            $response->setPublic();
            $response->setMaxAge(self::PUBLIC_MAX_AGE);
            $response->headers->addCacheControlDirective('must-revalidate');
        } else {
            $response->setPrivate();
            $response->setMaxAge(self::PRIVATE_MAX_AGE);
            $response->headers->addCacheControlDirective('immutable');
        }

        // Ahead of the decrypt on purpose: a conditional request never touches
        // the ciphertext.
        if ($response->isNotModified(request())) {
            return $response;
        }

        $response->setContent($this->storage->get($path));

        return $response;
    }

    /**
     * A filename the Content-Disposition fallback can carry: it must be plain
     * ASCII without quotes or percent signs, and must not be empty.
     */
    private function asciiFallbackName(ScoreFile $scoreFile): string
    {
        $ascii = preg_replace('/[^A-Za-z0-9._-]/', '_', $scoreFile->original_name) ?? '';
        $ascii = trim($ascii, '_');

        if ($ascii === '' || $ascii === '.' || $ascii === '..') {
            $extension = $scoreFile->extension();

            return 'score-'.$scoreFile->id.($extension === '' ? '' : '.'.$extension);
        }

        return $ascii;
    }
}
