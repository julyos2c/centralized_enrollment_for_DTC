<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsRoles;
use Tests\TestCase;

class RegistrarWorkspaceLayoutTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    public function test_student_and_alumni_search_are_outside_the_records_card(): void
    {
        $this->seedRoles();
        $this->actingAs($this->makeUserWithRole('registrar'));
        foreach (['students', 'alumni'] as $type) {
            $response = $this->get(route('registrar.students.index', ['type' => $type, 'per_page' => 5]))->assertOk();
            $dom = new \DOMDocument();
            $previous = libxml_use_internal_errors(true);
            $dom->loadHTML($response->getContent());
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            $xpath = new \DOMXPath($dom);
            $this->assertSame(1, $xpath->query('//form[@aria-label="Filter records"]/div//input[@name="q"]')->length);
            $this->assertSame(0, $xpath->query('//form[@aria-label="Filter records"]/ancestor::div[contains(concat(" ",normalize-space(@class)," ")," card ")]')->length);
            $this->assertSame(1, $xpath->query('//div[@id="recordsFilterPanel"]//select[@name="per_page"]')->length);
            $this->assertSame($type, $xpath->query('//form[@aria-label="Filter records"]/input[@name="type"]')->item(0)->getAttribute('value'));
        }
    }

    public function test_pending_enrollments_search_and_filter_placement(): void
    {
        $this->seedRoles();
        $registrar = $this->makeUserWithRole('registrar');
        $student = $this->makeUserWithRole('student');
        $program = \App\Models\Program::factory()->create(['name' => 'Information Systems', 'code' => 'BSIS']);
        \App\Models\Enrollment::factory()->pending()->create(['user_id' => $student->id, 'program_id' => $program->id]);
        \App\Models\Enrollment::factory()->create(['user_id' => $student->id, 'program_id' => $program->id]);
        foreach ([$student->name, $student->email, 'Information', 'BSIS'] as $search) {
            $this->actingAs($registrar)->get(route('registrar.enrollments.index', ['search' => $search, 'per_page' => 5]))
                ->assertOk()->assertViewHas('enrollments', fn ($rows) => $rows->total() === 1 && $rows->perPage() === 5 && $rows->first()->status === 'pending');
        }
        $response = $this->get(route('registrar.enrollments.index', ['search' => 'no-matching-record']))
            ->assertOk()->assertViewHas('enrollments', fn ($rows) => $rows->total() === 0);
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML($response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new \DOMXPath($dom);
        $this->assertSame(1, $xpath->query('//div[@id="enrollmentFilters"]//select[@name="per_page"]')->length);
        $this->assertSame(0, $xpath->query('//div[contains(@class,"table-responsive")]//select[@name="per_page"]')->length);
    }

    public function test_appointment_search_keeps_status_scope_and_row_limit(): void
    {
        $this->seedRoles();
        $registrar = $this->makeUserWithRole('registrar');
        $student = $this->makeUserWithRole('student');
        $slot = \App\Models\AppointmentSlot::create(['date' => '2026-10-26', 'start_time' => '08:00:00', 'end_time' => '08:30:00', 'max_bookings' => 5, 'source' => 'manual', 'status' => 'open']);
        foreach (['pending', 'confirmed'] as $status) {
            \App\Models\Appointment::create(['user_id' => $student->id, 'appointment_slot_id' => $slot->id, 'document_type' => 'tor', 'purpose' => 'Scholarship inquiry', 'status' => $status]);
        }
        foreach ([$student->name, $student->email, 'tor', 'Scholarship'] as $search) {
            $this->actingAs($registrar)->get(route('registrar.appointments.index', ['search' => $search, 'status' => 'pending', 'per_page' => 5]))
                ->assertOk()->assertViewHas('appointments', fn ($rows) => $rows->total() === 1 && $rows->perPage() === 5 && $rows->first()->status === 'pending');
        }
        $this->get(route('registrar.appointments.index', ['search' => 'no-matching-record']))
            ->assertOk()->assertViewHas('appointments', fn ($rows) => $rows->total() === 0)
            ->assertSee('id="appointmentFilters"', false);
    }

    public function test_registrar_workspaces_opt_into_redesign_but_dashboard_does_not(): void
    {
        $this->seedRoles();
        $registrar = $this->makeUserWithRole('registrar');
        $this->actingAs($registrar);
        foreach (['applications.index', 'enrollments.index', 'appointments.index', 'appointments.slots',
            'appointments.schedule', 'documents.index', 'students.index', 'imports.index'] as $page) {
            $this->get(route('registrar.' . $page))->assertOk()
                ->assertSee('css/registrar-workspace.css', false)
                ->assertSee('class="registrar-workspace', false);
        }
        $this->get(route('registrar.dashboard'))->assertOk()
            ->assertDontSee('css/registrar-workspace.css', false)
            ->assertDontSee('class="registrar-workspace', false);
    }

    public function test_application_row_limit_is_rendered_inside_filters_before_ajax_runs(): void
    {
        $this->seedRoles();
        $registrar = $this->makeUserWithRole('registrar');
        $html = $this->actingAs($registrar)->get(route('registrar.applications.index', ['per_page' => 5]))->assertOk()->getContent();
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new \DOMXPath($dom);
        $this->assertSame(1, $xpath->query('//form[@method="GET"]//select[@name="per_page"]')->length);
        $this->assertSame('5', $xpath->query('//select[@name="per_page"]/option[@selected]')->item(0)->getAttribute('value'));
    }
}
