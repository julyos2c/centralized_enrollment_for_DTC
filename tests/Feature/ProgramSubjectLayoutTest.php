<?php

namespace Tests\Feature;

use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsRoles;
use Tests\TestCase;

class ProgramSubjectLayoutTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    public function test_program_and_subject_views_do_not_reintroduce_generic_filters(): void
    {
        $this->seedRoles();
        $admin = $this->makeUserWithRole('admin');
        $program = Program::factory()->create();
        $this->actingAs($admin)->get(route('admin.programs.index', ['program' => $program->id]))
            ->assertOk()->assertSee($program->name)->assertDontSee('data-table-rows-control', false);
        $this->get(route('admin.programs.subjects', $program))
            ->assertOk()->assertSee('Add Subject')->assertDontSee('data-table-rows-control', false);
        $this->get(route('admin.programs.index', ['program' => $program->id]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertSee($program->name)->assertSee('Add Subject')->assertDontSee('data-table-rows-control', false);
    }
}
