<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;

class PrivateFilePreview
{
    public static function respond(
        Request $request,
        string $absolutePath,
        string $title,
        string $backUrl,
        string $fileName
    ) {
        if ($request->boolean('download')) {
            return Response::download($absolutePath, $fileName, [
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        if ($request->boolean('raw')) {
            return Response::file($absolutePath, [
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        $mimeType = File::mimeType($absolutePath) ?: 'application/octet-stream';

        return Response::view('shared.private-file-preview', [
            'title' => $title,
            'backUrl' => $backUrl,
            'fileName' => $fileName,
            'mimeType' => $mimeType,
            'isImage' => str_starts_with($mimeType, 'image/'),
            'previewUrl' => $request->fullUrlWithQuery(['raw' => 1]),
            'downloadUrl' => $request->fullUrlWithQuery(['raw' => 1, 'download' => 1]),
        ]);
    }
}
