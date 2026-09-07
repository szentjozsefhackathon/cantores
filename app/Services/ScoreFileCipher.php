<?php

namespace App\Services;

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

/**
 * The envelope every artifact under `score-files/` is stored in.
 *
 * Laravel's own `Crypt::encryptString()` was doing this job and costs 1.78
 * bytes on disk for every byte of score: `openssl_encrypt()` base64s its
 * output, then the {iv, value, mac} envelope is JSON-encoded and base64'd
 * again. For an occasional session payload that is invisible; for a library of
 * rendered pages and 300 dpi booklet strips it is nearly half the volume, and
 * it is paid again in memory every time a 25 MB upload is read or written.
 *
 * So the same protection is written down in binary instead:
 *
 *     "CSF1" | 12-byte nonce | 16-byte tag | ciphertext
 *
 * Thirty-two bytes flat, whatever the file's size. AES-256-GCM rather than the
 * application's AES-256-CBC because it authenticates in the same pass — a
 * separate HMAC would be one more thing to get wrong — and the version in the
 * magic is what names the algorithm, so a later format can be introduced
 * without guessing at what is already on disk.
 *
 * The key is the application's, taken from the encrypter rather than from
 * config, so `APP_PREVIOUS_KEYS` keeps working: writing uses the current key
 * and reading tries every key the application holds.
 *
 * Nothing binds a ciphertext to its path. Duplicating a score copies its
 * artifacts between directories without decrypting them, and an envelope that
 * refused to open anywhere but where it was written would break that.
 *
 * Anything without the magic is read as a Laravel envelope, so a library
 * written before this existed goes on opening; `scores:reencrypt` is what
 * moves it over.
 */
class ScoreFileCipher
{
    /** Names the format and the algorithm at once: version 1 is AES-256-GCM. */
    private const MAGIC = 'CSF1';

    private const CIPHER = 'aes-256-gcm';

    private const NONCE_BYTES = 12;

    private const TAG_BYTES = 16;

    /** What the envelope costs, whatever the file weighs. */
    public const OVERHEAD_BYTES = 32;

    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(self::NONCE_BYTES);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $this->keys()[0],
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            self::TAG_BYTES,
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Score file could not be encrypted.');
        }

        return self::MAGIC.$nonce.$tag.$ciphertext;
    }

    /**
     * @throws RuntimeException when no key opens the envelope, or it has been
     *                          altered since it was written
     */
    public function decrypt(string $blob): string
    {
        if (! $this->isOwnEnvelope($blob)) {
            return Crypt::decryptString($blob);
        }

        $nonce = substr($blob, strlen(self::MAGIC), self::NONCE_BYTES);
        $tag = substr($blob, strlen(self::MAGIC) + self::NONCE_BYTES, self::TAG_BYTES);
        $ciphertext = substr($blob, self::OVERHEAD_BYTES);

        foreach ($this->keys() as $key) {
            $plaintext = openssl_decrypt($ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $nonce, $tag);

            if ($plaintext !== false) {
                return $plaintext;
            }
        }

        throw new RuntimeException('Score file could not be decrypted.');
    }

    /**
     * Whether these bytes are already stored the way this class writes them.
     *
     * A Laravel envelope is base64 of a JSON object, so it begins `ey` and can
     * never be mistaken for the magic.
     */
    public function isOwnEnvelope(string $blob): bool
    {
        return strlen($blob) >= self::OVERHEAD_BYTES && str_starts_with($blob, self::MAGIC);
    }

    /**
     * The application's keys, current one first.
     *
     * @return list<string>
     */
    private function keys(): array
    {
        $encrypter = Crypt::getFacadeRoot();

        if (! $encrypter instanceof Encrypter) {
            throw new RuntimeException('Score files need Laravel\'s encrypter to reach the application key.');
        }

        return array_values($encrypter->getAllKeys());
    }
}
