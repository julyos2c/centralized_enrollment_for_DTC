<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentPayment;
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
            $this->actingAs($user)->get(route('portal.payments.document', $document))
                ->assertViewHas('canSubmit', false)
                ->assertSee('Walk-in payment')
                ->assertDontSee('name="proof_of_payment"', false);
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

    public function test_legacy_appointment_payment_details_show_one_valid_payment_state(): void
    {
        $user = $this->makeUserWithRole('student');
        $appointment = Appointment::factory()->create(['user_id' => $user->id]);
        $url = route('portal.payments.appointment', $appointment);

        Setting::set('appointment_processing_fee', 0);
        $this->actingAs($user)->get($url)->assertOk()
            ->assertViewHas('status', 'not_required')
            ->assertViewHas('canSubmit', false)
            ->assertSee('No Payment Required')
            ->assertDontSee('Walk-in payment')
            ->assertDontSee('GCash payment')
            ->assertDontSee('name="proof_of_payment"', false);
        $this->post(route('portal.appointments.payment.gcash', $appointment), [
            'reference_number' => 'NO-FEE',
            'proof_of_payment' => UploadedFile::fake()->image('proof.png'),
        ])->assertRedirect()->assertSessionHas('error');
        $this->assertSame(0, $appointment->payments()->count());

        Setting::set('appointment_processing_fee', 50);
        $this->get($url)->assertOk()
            ->assertViewHas('fee', 50.0)
            ->assertViewHas('canSubmit', true)
            ->assertSee('GCash payment')
            ->assertDontSee('Walk-in payment')
            ->assertSee('name="proof_of_payment"', false);

        AppointmentPayment::create([
            'appointment_id' => $appointment->id,
            'payment_method' => 'walk_in',
            'status' => 'pending',
            'amount' => 50,
        ]);
        $this->get($url)->assertOk()
            ->assertViewHas('canSubmit', false)
            ->assertSee('Walk-in payment')
            ->assertDontSee('GCash payment')
            ->assertDontSee('name="proof_of_payment"', false);
    }

    public function test_unpaid_walk_in_bundle_can_switch_to_gcash_from_payment_details(): void
    {
        $user = $this->makeUserWithRole('student');
        $appointment = Appointment::factory()->create(['user_id' => $user->id]);
        $document = $this->document($user, ['appointment_id' => $appointment->id]);
        $payment = AppointmentPayment::create([
            'appointment_id' => $appointment->id,
            'is_document_bundle' => true,
            'payment_method' => 'walk_in',
            'status' => 'pending',
            'amount' => 50,
        ]);
        $url = route('portal.payments.appointment', $appointment);

        $this->actingAs($user)->get($url)->assertOk()
            ->assertSee('Walk-in payment')
            ->assertSee('Prefer to pay online?')
            ->assertSee('Pay via GCash instead?')
            ->assertDontSee('name="proof_of_payment"', false);

        $this->post(route('portal.payments.appointments.switch-to-gcash', $appointment))
            ->assertRedirect($url)
            ->assertSessionMissing('success')
            ->assertSessionHasNoErrors();

        $this->assertSame('gcash', $payment->fresh()->payment_method);
        $this->assertSame('gcash_qr', $document->fresh()->payment_method);
        $this->assertSame('unpaid', $document->fresh()->payment_status);
        $this->get($url)->assertOk()
            ->assertSee('GCash payment')
            ->assertSee('dtc-gcash-account', false)
            ->assertSee('dtc-gcash-amount', false)
            ->assertSee('Save QR to phone')
            ->assertSee('dtc-file-label', false)
            ->assertSee('name="proof_of_payment"', false)
            ->assertSee('Back to requests')
            ->assertDontSee('Return to walk-in payment')
            ->assertDontSee('Pay via GCash instead');

        $this->post(route('portal.payments.appointments.back-to-requests', $appointment))
            ->assertRedirect(route('portal.appointments.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('walk_in', $payment->fresh()->payment_method);
        $this->assertSame('walk_in', $document->fresh()->payment_method);
        $this->get($url)->assertOk()->assertSee('Walk-in payment')->assertSee('Pay via GCash instead?')
            ->assertDontSee('name="proof_of_payment"', false);

        $this->post(route('portal.payments.appointments.switch-to-gcash', $appointment))
            ->assertRedirect($url)
            ->assertSessionHasNoErrors();

        $this->post(route('portal.appointments.bundle-payment.gcash', $appointment), [
            'reference_number' => 'SWITCH-TO-GCASH',
            'proof_of_payment' => UploadedFile::fake()->image('proof.png'),
        ])->assertRedirect($url)->assertSessionHasNoErrors();

        $this->assertSame('SWITCH-TO-GCASH', $payment->fresh()->reference_number);
        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame('pending_verification', $document->fresh()->payment_status);

        $this->post(route('portal.payments.appointments.back-to-requests', $appointment))
            ->assertRedirect(route('portal.appointments.index'))
            ->assertSessionHasNoErrors();
        $this->assertSame('gcash', $payment->fresh()->payment_method);
    }

    public function test_missing_qr_and_unapproved_enrollment_do_not_offer_online_payment(): void
    {
        $user = $this->makeUserWithRole('student');
        Storage::disk('local')->delete('qr.png');
        $document = $this->document($user, ['payment_method' => 'gcash_qr']);
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
