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
    public function schools(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        if (mb_strlen($term) < 2 || mb_strlen($term) > 100) {
            return response()->json(['results' => []]);
        }

        $schools = School::active()->search($term)->limit(15)->get()
            ->map(fn (School $school) => $school->toLookupArray());

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
