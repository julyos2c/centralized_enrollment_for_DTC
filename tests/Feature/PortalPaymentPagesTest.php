<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\DocumentRequest;
use App\Models\Enrollment;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\SeedsRoles;
use Tests\TestCase;

class PortalPaymentPagesTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Storage::fake('local');
        Setting::set('gcash_qr_path', 'qr.png');
        Storage::disk('local')->put('qr.png', 'qr');
    }

    private function document($user, array $attributes = []): DocumentRequest
    {
        return DocumentRequest::create(array_merge(['user_id' => $user->id, 'document_type' => 'tor',
            'copies' => 1, 'fee' => 50, 'status' => 'submitted', 'is_paid' => false,
            'payment_method' => 'walk_in', 'payment_status' => 'unpaid'], $attributes));
    }

    public function test_all_portal_roles_get_owned_pages_and_foreign_records_are_forbidden(): void
    {
        foreach (['student', 'alumni'] as $role) {
            $user = $this->makeUserWithRole($role);
            $document = $this->document($user);
            $appointment = Appointment::factory()->create(['user_id' => $user->id]);
            $enrollment = Enrollment::factory()->create(['user_id' => $user->id, 'status' => 'approved']);
            $this->actingAs($user)->get(route('portal.payments.document', $document))
                ->assertOk()
                ->assertViewIs('portal.payments.details')
                ->assertSee('css/portal-payments.css')
                ->assertDontSee('data-toggle="modal"', false);
            $this->get(route('portal.payments.appointment', $appointment))->assertOk()->assertViewIs('portal.payments.details');
            $this->get(route('portal.enrollment.payment-info', $enrollment))->assertOk()->assertViewIs('student.enrollment.payment')->assertDontSee('data-dismiss="modal"', false);
            $this->actingAs($this->makeUserWithRole($role));
            $this->get(route('portal.payments.document', $document))->assertForbidden();
            $this->get(route('portal.payments.appointment', $appointment))->assertForbidden();
            $this->get(route('portal.enrollment.payment-info', $enrollment))->assertForbidden();
        }
    }

    public function test_document_states_show_only_eligible_proof_forms_and_preserve_alumni_rules(): void
    {
        foreach (['student', 'alumni'] as $role) {
            $user = $this->makeUserWithRole($role);
            $document = $this->document($user);
            $this->actingAs($user)->get(route('portal.payments.document', $document))->assertViewHas('canSubmit', $role !== 'alumni');
            foreach (['pending_verification', 'verified', 'rejected'] as $status) {
                $document->update(['payment_status' => $status, 'payment_method' => 'gcash_qr']);
                $response = $this->get(route('portal.payments.document', $document))->assertOk();
                $response->assertViewHas('canSubmit', $status === 'rejected');
                if ($status === 'rejected') $response->assertSee('name="proof_of_payment"', false);
                else $response->assertDontSee('name="proof_of_payment"', false);
            }
            foreach (['cancelled', 'released'] as $status) {
                $document->update(['status' => $status]);
                $this->get(route('portal.payments.document', $document))->assertViewHas('canSubmit', false)->assertDontSee('name="proof_of_payment"', false);
            }
        }
    }

    public function test_linked_appointment_uses_document_payment_and_cancelled_appointments_cannot_pay(): void
    {
        $user = $this->makeUserWithRole('student');
        $appointment = Appointment::factory()->create(['user_id' => $user->id]);
        $document = $this->document($user, ['appointment_id' => $appointment->id]);
        $this->actingAs($user)->get(route('portal.payments.appointment', $appointment))->assertRedirect(route('portal.payments.document', $document));
        $other = Appointment::factory()->create(['user_id' => $user->id, 'status' => 'cancelled']);
        $this->get(route('portal.payments.appointment', $other))->assertViewHas('canSubmit', false)->assertDontSee('name="proof_of_payment"', false);
    }

    public function test_missing_qr_and_unapproved_enrollment_do_not_offer_online_payment(): void
    {
        $user = $this->makeUserWithRole('student');
        Storage::disk('local')->delete('qr.png');
        $document = $this->document($user);
        $this->actingAs($user)->get(route('portal.payments.document', $document))->assertOk()->assertSee('GCash is currently unavailable.')->assertDontSee('name="proof_of_payment"', false);
        $enrollment = Enrollment::factory()->create(['user_id' => $user->id, 'status' => 'pending']);
        $this->get(route('portal.enrollment.payment-info', $enrollment))->assertOk()->assertSee('Payment is available after')->assertDontSee('name="proof_of_payment"', false);
    }

    public function test_document_proof_submission_returns_each_role_to_its_payment_page(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        foreach (['student', 'alumni'] as $role) {
            $user = $this->makeUserWithRole($role);
            $document = $this->document($user, $role === 'alumni' ? ['payment_status' => 'rejected', 'payment_method' => 'gcash_qr'] : []);
            $url = route('portal.payments.document', $document);
            $post = route($role === 'alumni' ? 'portal.documents.payment.submit' : 'portal.appointments.document-payment.gcash', $document);
            $this->actingAs($user)->from($url)->post($post, [
                'reference_number' => 'GCASH-'.$role, 'proof_of_payment' => UploadedFile::fake()->image('proof.png'),
            ])->assertRedirect($url)->assertSessionHasNoErrors();
            $this->assertSame('pending_verification', $document->fresh()->payment_status);
            $this->get($url)->assertOk()->assertSee('Payment under review')->assertSee('View submitted proof')->assertDontSee('name="proof_of_payment"', false);
        }
    }
}
