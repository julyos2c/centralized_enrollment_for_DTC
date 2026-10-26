<?php

namespace App\Support;

/**
 * Cache-busting URLs for files in public/.
 *
 * The web server sends long cache lifetimes for static CSS/JS and Cloudflare
 * honours them, so an unchanged URL keeps serving the old file for up to a week
 * after a deploy. Appending a version derived from the file's modification time
 * and size gives every changed file a new URL, while unchanged files keep theirs.
 */
class AssetVersion
{
    /** @var array<string, string> */
    private static array $tokens = [];

    public static function url(string $path): string
    {
        $url = asset($path);
        $token = self::token($path);

        return $token === '' ? $url : $url . (str_contains($url, '?') ? '&' : '?') . 'v=' . $token;
    }

    public static function token(string $path): string
    {
        $path = ltrim($path, '/');

        if (! array_key_exists($path, self::$tokens)) {
            $file = public_path($path);
            self::$tokens[$path] = is_file($file)
                ? substr(md5(filemtime($file) . ':' . filesize($file)), 0, 10)
                : '';
        }

        return self::$tokens[$path];
    }
}
