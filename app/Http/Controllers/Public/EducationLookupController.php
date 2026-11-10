<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\ShsCurriculum;
use App\Support\AcademicYears;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Public lookups for the application form's education step. */
class EducationLookupController extends Controller
{
    /**
     * Selectable schools. With a province configured (Bohol) the whole list is
     * small, so it is returned at once (optionally for one municipality) and
     * filtered in the browser; ?q= searches by name or DepEd School ID.
     */
    public function schools(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        $city = (string) $request->query('city', '');
        if (mb_strlen($term) > 100 || ($city !== '' && !preg_match('/^\d{10}$/', $city))) {
            return response()->json(['results' => []], 422);
        }

        $query = School::selectable()->when($city !== '', fn ($q) => $q->where('city_code', $city));
        if ($term !== '') {
            if (mb_strlen($term) < 2) {
                return response()->json(['results' => []]);
            }
            $query->search($term)->limit(50);
        } elseif (config('schools.province_code')) {
            $query->orderBy('name')->limit(3000);
        } else {
            // Nationwide lists are too large to send whole; search instead.
            return response()->json(['results' => []]);
        }

        $schools = $query->get()->map(fn (School $school) => $school->toLookupArray())->values();

        return response()->json(['results' => $schools])
            ->header('Cache-Control', 'private, max-age=300');
    }

    /** Active curricula with their tracks and strands/clusters, plus year options. */
    public function classifications(): JsonResponse
    {
        $curricula = ShsCurriculum::active()
            ->with(['classifications' => fn ($query) => $query->active()])
            ->orderBy('sort_order')->get()
            ->map(fn (ShsCurriculum $curriculum) => [
                'id' => $curriculum->id,
                'code' => $curriculum->code,
                'name' => $curriculum->name,
                'description' => $curriculum->description,
                'choice_label' => $curriculum->choice_label,
                'first_graduation_year' => $curriculum->first_graduation_year,
                'last_graduation_year' => $curriculum->last_graduation_year,
                'classifications' => $curriculum->classifications->map(fn ($c) => [
                    'id' => $c->id,
                    'track' => $c->track,
                    'name' => $c->name,
                    'offering_key' => $c->offering_key,
                ])->values(),
            ])->values();

        return response()->json([
            'curricula' => $curricula,
            'current_school_year' => AcademicYears::current(),
        ])->header('Cache-Control', 'public, max-age=600');
    }
}
