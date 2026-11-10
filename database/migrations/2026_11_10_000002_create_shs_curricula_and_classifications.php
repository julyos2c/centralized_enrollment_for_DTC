<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Configurable SHS curricula and their track / strand (or cluster)
     * classifications. Seeded with:
     *  - the K to 12 SHS curriculum (first Grade 12 graduates in SY 2017-2018):
     *    Academic (ABM, HUMSS, STEM, GAS), TVL (its four strands), Sports,
     *    Arts and Design;
     *  - the Strengthened SHS Curriculum (DepEd DM 48, s. 2025; Grade 11
     *    pilot in SY 2025-2026, so the earliest graduates are SY 2026-2027):
     *    Academic and Technical-Professional tracks with their elective
     *    clusters. DepEd states clusters are not strands.
     * Staff can edit, add, or deactivate any of these later.
     */
    public function up(): void
    {
        Schema::create('shs_curricula', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->string('description', 500)->nullable();
            // Start year of the first / last school year a student could graduate under it.
            $table->unsignedSmallInteger('first_graduation_year')->nullable();
            $table->unsignedSmallInteger('last_graduation_year')->nullable();
            // What applicants choose under this curriculum: "Strand" or "Elective cluster".
            $table->string('choice_label', 40)->default('Strand');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('shs_classifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shs_curriculum_id')->constrained('shs_curricula')->restrictOnDelete();
            $table->string('track', 100);
            $table->string('code', 40);
            $table->string('name');
            // Matches a school's recorded offering (e.g. "STEM", "TVL") to suggest it first.
            $table->string('offering_key', 40)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['shs_curriculum_id', 'code']);
        });

        $now = now();
        $k12 = DB::table('shs_curricula')->insertGetId([
            'code' => 'k12_2016', 'name' => 'K to 12 SHS Curriculum',
            'description' => 'Tracks and strands used since Grade 11 began in SY 2016-2017.',
            'first_graduation_year' => 2017, 'last_graduation_year' => null,
            'choice_label' => 'Strand', 'sort_order' => 10, 'is_active' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $strengthened = DB::table('shs_curricula')->insertGetId([
            'code' => 'strengthened_2025', 'name' => 'Strengthened SHS Curriculum',
            'description' => 'Academic and Technical-Professional (TechPro) tracks with elective clusters (DepEd DM 48, s. 2025).',
            'first_graduation_year' => 2026, 'last_graduation_year' => null,
            'choice_label' => 'Elective cluster', 'sort_order' => 20, 'is_active' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $rows = [];
        $order = 0;
        $add = function (int $curriculum, string $track, string $code, string $name, ?string $offering) use (&$rows, &$order, $now) {
            $rows[] = [
                'shs_curriculum_id' => $curriculum, 'track' => $track, 'code' => $code, 'name' => $name,
                'offering_key' => $offering, 'sort_order' => $order += 10, 'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ];
        };

        $add($k12, 'Academic Track', 'ABM', 'Accountancy, Business and Management (ABM)', 'ABM');
        $add($k12, 'Academic Track', 'HUMSS', 'Humanities and Social Sciences (HUMSS)', 'HUMSS');
        $add($k12, 'Academic Track', 'STEM', 'Science, Technology, Engineering and Mathematics (STEM)', 'STEM');
        $add($k12, 'Academic Track', 'GAS', 'General Academic Strand (GAS)', 'GAS');
        $add($k12, 'Technical-Vocational-Livelihood (TVL) Track', 'TVL-HE', 'TVL – Home Economics', 'TVL');
        $add($k12, 'Technical-Vocational-Livelihood (TVL) Track', 'TVL-ICT', 'TVL – Information and Communications Technology (ICT)', 'TVL');
        $add($k12, 'Technical-Vocational-Livelihood (TVL) Track', 'TVL-IA', 'TVL – Industrial Arts', 'TVL');
        $add($k12, 'Technical-Vocational-Livelihood (TVL) Track', 'TVL-AFA', 'TVL – Agri-Fishery Arts', 'TVL');
        $add($k12, 'Technical-Vocational-Livelihood (TVL) Track', 'TVL', 'TVL – specialization not listed here', 'TVL');
        $add($k12, 'Sports Track', 'SPORTS', 'Sports Track', 'Sports');
        $add($k12, 'Arts and Design Track', 'ARTS', 'Arts and Design Track', 'Arts and Design');

        $order = 0;
        foreach ([
            'ASSH' => 'Arts, Social Sciences, and Humanities',
            'BE' => 'Business and Entrepreneurship',
            'STEM' => 'Science, Technology, Engineering, and Mathematics',
            'SHW' => 'Sports, Health, and Wellness',
        ] as $code => $name) {
            $add($strengthened, 'Academic Track', 'ACAD-' . $code, $name, null);
        }
        foreach ([
            'AWHC' => 'Aesthetic, Wellness, and Human Care',
            'AFBFI' => 'Agri-Fishery Business and Food Innovation',
            'ACE' => 'Artisanry and Creative Enterprise',
            'ASET' => 'Automotive and Small Engine Technologies',
            'CBT' => 'Construction and Building Technologies',
            'CADT' => 'Creative Arts and Design Technologies',
            'HT' => 'Hospitality and Tourism',
            'ICT' => 'ICT Support and Computer Programming Technologies',
            'IT' => 'Industrial Technologies',
            'MT' => 'Maritime Transport',
        ] as $code => $name) {
            $add($strengthened, 'Technical-Professional (TechPro) Track', 'TP-' . $code, $name, null);
        }

        DB::table('shs_classifications')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('shs_classifications');
        Schema::dropIfExists('shs_curricula');
    }
};
