<?php

use App\Support\VendorAsset;

/**
 * The vendored abc2svg lives at one stable URL and is served without a
 * `Cache-Control` header, so a browser is free to reuse its copy on heuristic
 * freshness alone. A patch applied to it (see `docs/vendor-patches.md`) then
 * stays invisible — silently, because abc2svg ignores a directive it does not
 * know. Versioning the URL by mtime is what makes a patch land.
 */
it('versions a public asset by its mtime', function () {
    $url = VendorAsset::url('js/abc2svg-1.js');

    expect($url)->toStartWith(asset('js/abc2svg-1.js').'?v=')
        ->and($url)->toEndWith((string) filemtime(public_path('js/abc2svg-1.js')));
});

it('changes the URL when the file changes', function () {
    $path = public_path('js/abc2svg-1.js');
    $before = VendorAsset::url('js/abc2svg-1.js');

    $original = filemtime($path);

    try {
        touch($path, $original + 1);
        clearstatcache(true, $path);

        expect(VendorAsset::url('js/abc2svg-1.js'))->not->toBe($before);
    } finally {
        touch($path, $original);
        clearstatcache(true, $path);
    }
});

it('falls back to an unversioned URL for a file that is not there', function () {
    expect(VendorAsset::url('js/not-a-real-file.js'))
        ->toBe(asset('js/not-a-real-file.js'));
});

it('loads abc2svg through the versioned URL everywhere', function () {
    $views = glob(resource_path('views/livewire/pages/*.blade.php'));
    $loaders = array_filter(
        $views,
        fn (string $view): bool => str_contains(file_get_contents($view), 'abc2svg-1.js'),
    );

    expect($loaders)->not->toBeEmpty();

    foreach ($loaders as $view) {
        expect(file_get_contents($view))
            ->toContain("VendorAsset::url('js/abc2svg-1.js')")
            ->not->toContain("asset('js/abc2svg-1.js')");
    }
});
