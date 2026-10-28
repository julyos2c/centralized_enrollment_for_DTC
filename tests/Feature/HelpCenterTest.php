<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\SeedsRoles;

class HelpCenterTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_guests_must_sign_in(): void
    {
        $this->get(route('help.index'))->assertRedirect(route('login'));
    }

    public function test_all_roles_have_help_and_only_authorized_topic_links(): void
    {
        foreach (['admin', 'registrar', 'cashier', 'student', 'alumni', 'new_applicant'] as $role) {
            $user = $this->makeUserWithRole($role);
            $response = $this->actingAs($user)->get(route('help.index'))->assertOk()
                ->assertSee('Help Center')->assertSee('Visit the Registrar’s Office for assistance.');
            $topics = $response->viewData('topics');
            foreach ($topics as $topic) {
                $this->assertTrue(\Illuminate\Support\Facades\Route::has($topic['route']));
            }
            $routes = $topics->pluck('route');
            if ($role === 'new_applicant') {
                $this->assertFalse($routes->contains('portal.appointments.create'));
                $this->assertFalse($routes->contains('registrar.documents.index'));
            }
            if ($role === 'alumni') $this->assertFalse($routes->contains('portal.enrollment.create'));
            if ($role === 'student') $this->assertFalse($routes->contains('cashier.document-payments.index'));
            $this->assertStringContainsString(route('help.index'), $response->getContent());
        }
    }

    public function test_search_filters_after_role_selection_and_escapes_input(): void
    {
        $this->actingAs($this->makeUserWithRole('student'));
        $this->get(route('help.index', ['q' => 'bundled document payments']))->assertOk()
            ->assertSee('No matching topics')->assertDontSee('How should bundled document payments be verified?');
        $this->get(route('help.index', ['q' => '<script>alert(1)</script>']))->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)->assertSee('No matching topics');
        $this->get(route('help.index', ['q' => 'pickup']))->assertOk()->assertViewHas('topics', fn ($topics) => $topics->isNotEmpty());
        $this->get(route('help.index', ['q' => str_repeat('x', 151)]))->assertSessionHasErrors('q');
    }

    public function test_category_filter_respects_role_visibility_and_search(): void
    {
        $this->actingAs($this->makeUserWithRole('student'));
        $this->get(route('help.index', ['category' => 'Payments', 'q' => 'GCash']))
            ->assertOk()->assertViewHas('topics', fn ($topics) => $topics->isNotEmpty()
                && $topics->every(fn ($topic) => $topic['category'] === 'Payments'));
        $this->get(route('help.index', ['category' => 'Registrar']))->assertOk()
            ->assertViewHas('topics', fn ($topics) => $topics->isEmpty())
            ->assertViewHas('categories', fn ($categories) => !$categories->contains('Registrar'));
    }

}
