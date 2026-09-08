<?php

namespace App\Services;

use App\Models\ScoreFile;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Reads and writes everything under `score-files/`, encrypted at rest.
 *
 * The backup snapshot in docs/backup-setup.md tars the storage volume into a
 * bucket shared with other backup processes, and APP_KEY is not in that
 * snapshot — it lives on the host in .env.prod. Encrypting here means a leaked
 * snapshot or a stolen volume copy yields ciphertext only. It buys nothing
 * against a compromise of the host itself, where the key sits beside the data.
 *
 * The cost is that losing APP_KEY loses the library irrecoverably, so the key
 * belongs in the password manager next to RESTIC_PASSWORD.
 *
 * What the envelope looks like is ScoreFileCipher's business; this class only
 * knows that bytes go in and come back.
 */
class ScoreFileStorage
{
    public const DISK = 'private';

    public function __construct(private readonly ScoreFileCipher $cipher) {}

    public function disk(): Filesystem
    {
        return Storage::disk(self::DISK);
    }

    public function put(string $path, string $bytes): void
    {
        $this->disk()->put($path, $this->cipher->encrypt($bytes));
    }

    /**
     * The decrypted bytes at a path.
     *
     * @throws RuntimeException when the file is missing or does not decrypt
     */
    public function get(string $path): string
    {
        $ciphertext = $this->disk()->get($path);

        if ($ciphertext === null) {
            throw new RuntimeException("Score file missing: {$path}");
        }

        try {
            return $this->cipher->decrypt($ciphertext);
        } catch (\Throwable $e) {
            throw new RuntimeException("Score file could not be decrypted: {$path}", previous: $e);
        }
    }

    public function exists(string $path): bool
    {
        return $this->disk()->exists($path);
    }

    /**
     * Decrypt a stored file to a plaintext path outside the storage volume.
     *
     * Renderers are real binaries reading real files, so the plaintext has to
     * exist somewhere; callers pass a path inside the isolated 0700 work
     * directory they clean up in a `finally`, which is tmpfs-backed on the
     * renderer service so it never lands on the volume being protected.
     */
    public function extractTo(string $path, string $destination): void
    {
        if (file_put_contents($destination, $this->get($path)) === false) {
            throw new RuntimeException("Unable to write decrypted score file to {$destination}.");
        }
    }

    /**
     * Drop every artifact belonging to a score file.
     */
    public function deleteAll(ScoreFile $scoreFile): void
    {
        $this->disk()->deleteDirectory($scoreFile->directory());
    }

    /**
     * Drop the artifacts in a score file's directory whose basename matches one
     * of the given `fnmatch` patterns.
     *
     * A re-render switches a file between the raster and vector representations;
     * this is how the one it no longer uses stops taking up room, so the library
     * conversion realises its saving rather than doubling storage.
     *
     * @param  list<string>  $patterns
     */
    public function deleteMatching(ScoreFile $scoreFile, array $patterns): void
    {
        foreach ($this->disk()->files($scoreFile->directory()) as $path) {
            $name = basename($path);

            foreach ($patterns as $pattern) {
                if (fnmatch($pattern, $name)) {
                    $this->disk()->delete($path);
                    break;
                }
            }
        }
    }
}
