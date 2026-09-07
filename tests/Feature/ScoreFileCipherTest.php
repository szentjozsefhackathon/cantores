<?php

use App\Services\ScoreFileCipher;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;

test('what goes in comes back out', function () {
    $cipher = new ScoreFileCipher;

    foreach (['', 'a', 'a page of music', str_repeat('x', 100_000)] as $plaintext) {
        expect($cipher->decrypt($cipher->encrypt($plaintext)))->toBe($plaintext);
    }
});

// PNGs and PDFs, not text: the envelope has to survive null bytes and anything
// that would have needed escaping.
test('binary survives it byte for byte', function () {
    $cipher = new ScoreFileCipher;
    $plaintext = random_bytes(65_536)."\x00\x00\xff\x89PNG\r\n\x1a\n";

    expect($cipher->decrypt($cipher->encrypt($plaintext)))->toBe($plaintext);
});

// The whole reason for the change: 32 bytes flat instead of 78% of the file.
test('the envelope costs thirty-two bytes whatever the file weighs', function () {
    $cipher = new ScoreFileCipher;

    foreach ([0, 1, 1_000, 250_000] as $size) {
        $plaintext = $size === 0 ? '' : random_bytes($size);

        expect(strlen($cipher->encrypt($plaintext)) - $size)->toBe(ScoreFileCipher::OVERHEAD_BYTES);
    }
});

test('it is smaller than what Laravel would have written', function () {
    $plaintext = random_bytes(200_000);

    $ours = strlen((new ScoreFileCipher)->encrypt($plaintext));
    $laravel = strlen(Crypt::encryptString($plaintext));

    expect($ours)->toBeLessThan((int) ($laravel * 0.6));
});

test('the same bytes never encrypt to the same envelope twice', function () {
    $cipher = new ScoreFileCipher;

    expect($cipher->encrypt('a page of music'))->not->toBe($cipher->encrypt('a page of music'));
});

// Authenticated, not merely encrypted: the volume is the thing being defended,
// so bytes changed on it must fail loudly rather than decrypt to rubbish.
test('an altered envelope refuses to open', function (int $offset) {
    $cipher = new ScoreFileCipher;
    $blob = $cipher->encrypt(str_repeat('a page of music. ', 100));

    $position = $offset < 0 ? strlen($blob) + $offset : $offset;
    $blob[$position] = $blob[$position] === "\x00" ? "\x01" : "\x00";

    expect(fn () => $cipher->decrypt($blob))->toThrow(RuntimeException::class);
})->with([
    'the nonce' => [4],
    'the tag' => [16],
    'the ciphertext' => [40],
    'the last byte' => [-1],
]);

test('a truncated envelope refuses to open', function () {
    $cipher = new ScoreFileCipher;
    $blob = $cipher->encrypt(str_repeat('a page of music. ', 100));

    expect(fn () => $cipher->decrypt(substr($blob, 0, 200)))->toThrow(RuntimeException::class);
});

// A library written before this existed has to go on opening, or the change
// would take the whole collection offline until a backfill finished.
test('it still opens what Laravel wrote', function () {
    $plaintext = random_bytes(4_096);

    expect((new ScoreFileCipher)->decrypt(Crypt::encryptString($plaintext)))->toBe($plaintext);
});

test('it can tell its own envelopes from Laravel\'s', function () {
    $cipher = new ScoreFileCipher;

    expect($cipher->isOwnEnvelope($cipher->encrypt('music')))->toBeTrue()
        ->and($cipher->isOwnEnvelope(Crypt::encryptString('music')))->toBeFalse()
        ->and($cipher->isOwnEnvelope(''))->toBeFalse()
        ->and($cipher->isOwnEnvelope('CSF1'))->toBeFalse();
});

// APP_PREVIOUS_KEYS is how a key is rotated without rewriting the library, and
// it has to keep working across the new envelope too.
test('a previous key still opens what it wrote', function () {
    $cipher = new ScoreFileCipher;
    $old = Crypt::getFacadeRoot()->getKey();
    $blob = $cipher->encrypt('a page of music');

    Crypt::swap(new Encrypter(random_bytes(32), 'AES-256-CBC'));
    Crypt::getFacadeRoot()->previousKeys([$old]);

    expect($cipher->decrypt($blob))->toBe('a page of music');
});

test('a key that never wrote it does not open it', function () {
    $cipher = new ScoreFileCipher;
    $blob = $cipher->encrypt('a page of music');

    Crypt::swap(new Encrypter(random_bytes(32), 'AES-256-CBC'));

    expect(fn () => $cipher->decrypt($blob))->toThrow(RuntimeException::class);
});
