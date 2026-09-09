<?php

namespace App\Support;

/**
 * A cache-busting URL for a hand-maintained asset under `public/`.
 *
 * Vite's own output is content-hashed, so a rebuilt bundle always arrives at a
 * new URL. The vendored scripts in `public/js` are not: they keep one stable
 * URL for their whole life, and Apache serves them with no `Cache-Control` at
 * all. A browser therefore falls back to heuristic freshness and will reuse its
 * copy without revalidating — so a vendor patch applied here (see
 * `docs/vendor-patches.md`) can stay invisible in a browser that already holds
 * the pre-patch file, with no error to show for it.
 *
 * Appending the file's mtime gives each edit a URL of its own, which is what
 * makes a patch take effect the moment it lands.
 */
class VendorAsset
{
    /**
     * @param  string  $path  Path under `public/`, e.g. `js/abc2svg-1.js`.
     */
    public static function url(string $path): string
    {
        $version = @filemtime(public_path($path));

        return asset($path).($version ? '?v='.$version : '');
    }
}
