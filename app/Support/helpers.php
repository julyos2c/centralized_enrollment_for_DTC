<?php

use App\Support\AssetVersion;

if (! function_exists('asset_v')) {
    /**
     * asset() with a cache-busting version for a local public file,
     * e.g. asset_v('css/dtc-theme.css') => https://…/css/dtc-theme.css?v=1a2b3c4d5e
     */
    function asset_v(string $path): string
    {
        return AssetVersion::url($path);
    }
}
