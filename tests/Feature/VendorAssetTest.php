<?php

use App\Support\VendorAsset;
use Illuminate\Support\Facades\File;

/**
 * abc2svg lives at one stable URL and is served without a
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

it('serves the engraving engines from their installed packages', function (string $asset, string $packageAsset) {
    expect(is_link(public_path($asset)))->toBeTrue()
        ->and(realpath(public_path($asset)))->toBe(realpath(base_path($packageAsset)));
})->with([
    'abc2svg' => ['js/abc2svg-1.js', 'node_modules/@cantoreshu/abc2svg/abc2svg-1.js'],
    'exsurge' => ['js/exsurge.min.js', 'node_modules/exsurge/dist/exsurge.min.js'],
    'exsurge source map' => ['js/exsurge.min.js.map', 'node_modules/exsurge/dist/exsurge.min.js.map'],
]);

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

it('loads each engine through the versioned URL everywhere', function (string $asset) {
    $views = glob(resource_path('views/livewire/pages/*.blade.php'));
    $loaders = array_filter(
        $views,
        fn (string $view): bool => str_contains(file_get_contents($view), basename($asset)),
    );

    expect($loaders)->not->toBeEmpty();

    foreach ($loaders as $view) {
        expect(file_get_contents($view))
            ->toContain("VendorAsset::url('{$asset}')")
            ->not->toContain("asset('{$asset}')");
    }
})->with(['js/abc2svg-1.js', 'js/exsurge.min.js']);

/**
 * A third-party script is refused storage by Edge's Tracking Prevention, which
 * logs a warning for every access, and it is one more origin that can be down.
 */
it('loads no engine from a CDN', function () {
    foreach (File::allFiles(resource_path('views')) as $view) {
        expect($view->getContents())->not->toContain('cdn.jsdelivr.net');
    }
});
