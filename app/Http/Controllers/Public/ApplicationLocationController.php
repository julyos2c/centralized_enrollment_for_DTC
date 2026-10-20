<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\PsgcLocationCatalog;
use Illuminate\Http\JsonResponse;

class ApplicationLocationController extends Controller
{
    public function __construct(private PsgcLocationCatalog $locations)
    {
    }

    public function regions(): JsonResponse
    {
        return response()->json($this->locations->regions())->header('Cache-Control', 'public, max-age=86400');
    }

    public function areas(string $region): JsonResponse
    {
        abort_unless($this->locations->region($region), 404);

        return response()->json($this->locations->areas($region))->header('Cache-Control', 'public, max-age=86400');
    }

    public function cities(string $province): JsonResponse
    {
        abort_unless($this->locations->cities($province), 404);

        return response()->json($this->locations->cities($province))->header('Cache-Control', 'public, max-age=86400');
    }
}
