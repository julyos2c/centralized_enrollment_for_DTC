<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentSlot;
use App\Models\DocumentRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\SeedsRoles;
use Tests\TestCase;

class PrecommitFlowRegressionTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Mail::fake();
        $this->travelTo(\Carbon\Carbon::parse('2026-10-26 07:00:00', 'Asia/Manila'));
    }

    private function document(User $user, array $attributes = []): DocumentRequest
    {
        return DocumentRequest::create(array_merge([
            'user_id' => $user->id, 'document_type' => 'tor', 'copies' => 1,
            'purpose' => 'Employment', 'status' => 'submitted', 'fee' => 50,
            'is_paid' => false, 'payment_method' => 'walk_in', 'payment_status' => 'unpaid',
        ], $attributes));
    }

    private function slot(): AppointmentSlot
    {
        return AppointmentSlot::create(['date' => '2026-10-27', 'start_time' => '09:00:00', 'end_time' => '09:30:00', 'max_bookings' => 5, 'source' => 'manual', 'status' => 'open']);
    }

    public function test_registrar_filters_search_entire_dataset_before_pagination(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $alumni = $this->makeUserWithRole('alumni');
        $target = $this->document($alumni, ['status' => 'ready', 'purpose' => 'Special scholarship', 'created_at' => now()->subDay()]);
        $target->forceFill(['created_at' => now()->subDay()])->save();
        for ($i = 0; $i < 12; $i++) $this->document($alumni, ['document_type' => 'diploma']);
        $this->actingAs($registrar)->get(route('registrar.documents.index', ['per_page' => 5]))
            ->assertOk()->assertViewHas('requests', fn ($rows) => !$rows->contains('id', $target->id) && $rows->total() === 13);
        foreach (['Special scholarship', 'Transcript of Records', $alumni->email] as $search) {
            $this->get(route('registrar.documents.index', ['search' => $search, 'status' => 'ready', 'per_page' => 5]))
                ->assertOk()->assertViewHas('requests', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $target->id && $rows->perPage() === 5)
                ->assertViewHas('statusCounts', fn ($counts) => (int) $counts['ready'] === 1);
        }
        $this->get(route('registrar.documents.index', ['search' => "' OR 1=1 --"]))
            ->assertOk()->assertViewHas('requests', fn ($rows) => $rows->total() === 0);
        $this->getJson(route('registrar.documents.index', ['status' => 'invalid']))->assertUnprocessable();
        $this->actingAs($alumni)->get(route('registrar.documents.index'))->assertForbidden();
    }

    public function test_document_pagination_links_preserve_filters_and_tabs_reset_page(): void
    {
        $user = $this->makeUserWithRole('alumni');
        for ($i = 0; $i < 7; $i++) $this->document($user, ['status' => 'ready']);
        $response = $this->actingAs($this->makeUserWithRole('registrar'))->get(route('registrar.documents.index', ['search' => 'Employment', 'status' => 'ready', 'per_page' => 5, 'page' => 2]))->assertOk();
        $response->assertViewHas('requests', fn ($rows) => $rows->total() === 7 && $rows->count() === 2 && str_contains($rows->url(1), 'search=Employment'));
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML($response->getContent());
        libxml_clear_errors(); libxml_use_internal_errors($previous);
        $xpath = new \DOMXPath($dom);
        foreach ($xpath->query('//nav[@aria-label="Filter by status"]/a') as $link) {
            parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);
            $this->assertSame('Employment', $query['search']);
            $this->assertSame('5', $query['per_page']);
            $this->assertArrayNotHasKey('page', $query);
        }
    }

    public function test_only_linked_requests_show_document_preparation_tracker(): void
    {
        $student = $this->makeUserWithRole('student');
        $appointment = Appointment::factory()->create(['user_id' => $student->id, 'appointment_slot_id' => $this->slot()->id, 'status' => 'confirmed']);
        $this->actingAs($student)->get(route('portal.appointments.index'))->assertOk()
            ->assertSee('Appointment confirmed')->assertDontSee('class="dr-track"', false)->assertDontSee('Preparing');
        $this->document($student, ['appointment_id' => $appointment->id, 'status' => 'processing']);
        $this->get(route('portal.appointments.index'))->assertOk()->assertSee('class="dr-track"', false)->assertSee('Preparing');
    }

    public function test_cancelled_ready_paid_pickup_allows_self_service_rebooking_and_prevents_duplicates(): void
    {
        $alumni = $this->makeUserWithRole('alumni');
        $slot = $this->slot();
        $old = Appointment::factory()->create(['user_id' => $alumni->id, 'appointment_slot_id' => $slot->id, 'status' => 'cancelled']);
        $request = $this->document($alumni, ['appointment_id' => $old->id, 'status' => 'ready', 'is_paid' => true, 'payment_status' => 'verified']);
        $this->actingAs($alumni)->get(route('portal.documents.index'))->assertOk()->assertSee('Choose a new pickup time')->assertDontSee('Contact the Registrar to rebook.');
        $url = route('portal.documents.pickup-appointment', $request);
        $this->post($url, ['appointment_slot_id' => $slot->id])->assertSessionHasNoErrors()->assertRedirect(route('portal.documents.index'));
        $newId = $request->fresh()->appointment_id;
        $this->assertNotSame($old->id, $newId);
        $this->assertSame('cancelled', $old->fresh()->status);
        $this->post($url, ['appointment_slot_id' => $slot->id])->assertSessionHasErrors('appointment_slot_id');
        $this->assertSame($newId, $request->fresh()->appointment_id);
        $this->actingAs($this->makeUserWithRole('alumni'))->post($url, ['appointment_slot_id' => $slot->id])->assertForbidden();
    }

    public function test_unpaid_or_unready_requests_cannot_rebook_cancelled_pickups(): void
    {
        $alumni = $this->makeUserWithRole('alumni');
        $slot = $this->slot();
        foreach ([['status' => 'ready'], ['status' => 'processing', 'is_paid' => true, 'payment_status' => 'verified']] as $attributes) {
            $old = Appointment::factory()->create(['user_id' => $alumni->id, 'appointment_slot_id' => $slot->id, 'status' => 'cancelled']);
            $request = $this->document($alumni, array_merge(['appointment_id' => $old->id], $attributes));
            $this->actingAs($alumni)->post(route('portal.documents.pickup-appointment', $request), ['appointment_slot_id' => $slot->id])->assertSessionHasErrors('appointment_slot_id');
            $this->assertSame($old->id, $request->fresh()->appointment_id);
        }
    }

    public function test_cancelled_pickup_without_available_slots_explains_registrar_assistance(): void
    {
        $alumni = $this->makeUserWithRole('alumni');
        $slot = $this->slot();
        $slot->update(['status' => 'closed']);
        $old = Appointment::factory()->create(['user_id' => $alumni->id, 'appointment_slot_id' => $slot->id, 'status' => 'cancelled']);
        $request = $this->document($alumni, ['appointment_id' => $old->id, 'status' => 'ready', 'is_paid' => true, 'payment_status' => 'verified']);
        $this->actingAs($alumni)->get(route('portal.documents.index'))->assertOk()
            ->assertSee('Contact the Registrar for available pickup times.')->assertDontSee('data-pickup-form', false);
        $this->post(route('portal.documents.pickup-appointment', $request), ['appointment_slot_id' => $slot->id])->assertSessionHasErrors('appointment_slot_id');
    }
}
