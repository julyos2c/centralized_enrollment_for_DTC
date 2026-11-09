<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\PsgcLocationCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public, read-only PSGC lookups for the application form. Responses come
 * from local files (see PsgcLocationCatalog) and carry long-lived cache
 * headers; the form adds ?v={data version} so a data refresh is picked up
 * immediately.
 */
class ApplicationLocationController extends Controller
{
    public function __construct(private PsgcLocationCatalog $locations)
    {
    }

    public function regions(Request $request): JsonResponse
    {
        return $this->cached($request, $this->locations->regions());
    }

    public function areas(Request $request, string $region): JsonResponse
    {
        abort_unless($this->locations->region($region), 404);

        return $this->cached($request, $this->locations->areas($region));
    }

    public function cities(Request $request, string $province): JsonResponse
    {
        abort_unless($this->locations->cities($province), 404);

        return $this->cached($request, $this->locations->cities($province));
    }

    /** Barangays and postal codes for one city or municipality. */
    public function cityDetails(Request $request, string $city): JsonResponse
    {
        abort_unless($this->locations->cityByCode($city), 404);

        return $this->cached($request, [
            'barangays' => $this->locations->barangays($city),
            'postal_codes' => $this->locations->postalCodes($city),
        ]);
    }

    private function cached(Request $request, array $payload): JsonResponse
    {
        $etag = '"' . $this->locations->version() . '-' . md5($request->path()) . '"';
        if (in_array($etag, $request->getETags(), true)) {
            return response()->json(null, 304)->setEtag(trim($etag, '"'))
                ->header('Cache-Control', 'public, max-age=' . config('psgc.http_max_age'));
        }

        return response()->json($payload)
            ->setEtag(trim($etag, '"'))
            ->header('Cache-Control', 'public, max-age=' . config('psgc.http_max_age'))
            ->header('X-PSGC-Version', $this->locations->version());
    }
}
