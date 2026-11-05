<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Program;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsRoles;
use Tests\TestCase;

class PortalCorEnrollmentSelectionTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_requested_paid_enrollment_is_used_for_cor_and_download_link(): void
    {
        $student = $this->makeUserWithRole('student');
        Setting::set('current_school_year', '2026-2027');

        $currentProgram = Program::factory()->create(['name' => 'Current Program']);
        Enrollment::factory()->paid()->create([
            'user_id' => $student->id,
            'program_id' => $currentProgram->id,
            'school_year' => '2026-2027',
        ]);

        $historicalProgram = Program::factory()->create(['name' => 'Historical Program']);
        $historicalEnrollment = Enrollment::factory()->paid()->create([
            'user_id' => $student->id,
            'program_id' => $historicalProgram->id,
            'school_year' => '2025-2026',
        ]);

        $this->actingAs($student)
            ->get(route('portal.cor.show', ['enrollment' => $historicalEnrollment->id]))
            ->assertOk()
            ->assertSee('Historical Program')
            ->assertDontSee('Current Program')
            ->assertSee('href="'.route('portal.cor.download', ['enrollment' => $historicalEnrollment->id]).'"', false);
    }

    public function test_cor_enrollment_parameter_cannot_select_another_students_enrollment(): void
    {
        $student = $this->makeUserWithRole('student');
        $otherStudent = $this->makeUserWithRole('student');
        $program = Program::factory()->create(['name' => 'Private Program']);
        $enrollment = Enrollment::factory()->paid()->create([
            'user_id' => $otherStudent->id,
            'program_id' => $program->id,
        ]);

        $this->actingAs($student)
            ->get(route('portal.cor.show', ['enrollment' => $enrollment->id]))
            ->assertRedirect(route('portal.dashboard'))
            ->assertSessionHas('error');
    }

    public function test_cor_enrollment_parameter_rejects_unpaid_enrollments(): void
    {
        $student = $this->makeUserWithRole('student');
        $program = Program::factory()->create();
        $enrollment = Enrollment::factory()->create([
            'user_id' => $student->id,
            'program_id' => $program->id,
            'status' => 'approved',
            'is_paid' => false,
        ]);

        $this->actingAs($student)
            ->get(route('portal.cor.show', ['enrollment' => $enrollment->id]))
            ->assertRedirect(route('portal.dashboard'))
            ->assertSessionHas('error');
    }
}
