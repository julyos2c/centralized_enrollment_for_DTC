<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\UserNotification;
use App\Services\StudentWelcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\SeedsRoles;
use Tests\TestCase;

class StudentWelcomeTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Mail::fake();
    }

    public function test_both_real_payment_promotions_create_one_event_and_permanent_notification(): void
    {
        $cashier = $this->makeUserWithRole('cashier');
        foreach (['walk_in', 'gcash'] as $method) {
            $user = $this->makeUserWithRole('new_applicant');
            $enrollment = Enrollment::factory()->create(['user_id' => $user->id, 'status' => 'approved', 'is_paid' => false]);
            Application::create(['reference_no' => 'WELCOME-'.Str::random(10), 'user_id' => $user->id,
                'program_id' => $enrollment->program_id, 'first_name' => 'Test', 'last_name' => 'Applicant',
                'email' => $user->email, 'gender' => 'female', 'birthdate' => '2005-01-01',
                'academic_status' => 'new_student', 'status' => 'approved']);
            $this->actingAs($user)->getJson(route('notifications.poll'))->assertJsonPath('student_welcome', null);
            $this->assertDatabaseMissing('student_welcomes', ['user_id' => $user->id]);
            if ($method === 'walk_in') {
                $this->actingAs($cashier)->post(route('cashier.payments.store', $enrollment))->assertRedirect();
            } else {
                $payment = Payment::factory()->create(['enrollment_id' => $enrollment->id,
                    'payment_method' => 'gcash', 'status' => 'pending']);
                $this->actingAs($cashier)->post(route('cashier.payments.verify', $payment))->assertRedirect();
            }
            $this->assertTrue($user->fresh()->hasRole('student'));
            $this->assertDatabaseHas('student_welcomes', ['user_id' => $user->id, 'acknowledged_at' => null]);
            StudentWelcome::record($user->fresh());
            $this->assertSame(1, DB::table('student_welcomes')->where('user_id', $user->id)->count());
            $this->assertSame(1, UserNotification::where('user_id', $user->id)->where('event_key', 'student-promotion:'.$user->id)->count());
            $this->actingAs($user)->getJson(route('notifications.poll'))->assertJsonPath('student_welcome.url', route('portal.dashboard'));
            $this->actingAs($cashier)->getJson(route('notifications.poll'))->assertJsonPath('student_welcome', null);
        }
    }

    public function test_claims_are_private_exclusive_recoverable_and_acknowledgement_prevents_replay(): void
    {
        $user = $this->makeUserWithRole('student');
        $other = $this->makeUserWithRole('student');
        StudentWelcome::record($user);
        $first = (string) Str::uuid();
        $second = (string) Str::uuid();
        $url = route('notifications.student-welcome');
        $this->actingAs($other)->getJson(route('notifications.poll'))->assertJsonPath('student_welcome', null);
        $this->postJson($url, ['action' => 'acknowledge', 'token' => $first])->assertJsonPath('accepted', false);
        $this->actingAs($user)->postJson($url, ['action' => 'claim', 'token' => $first])->assertJsonPath('accepted', true);
        $this->getJson(route('notifications.poll'))->assertJsonPath('student_welcome.available', false);
        $this->postJson($url, ['action' => 'claim', 'token' => $second])->assertJsonPath('accepted', false);
        $this->postJson($url, ['action' => 'acknowledge', 'token' => $second])->assertJsonPath('accepted', false);
        $this->travel(31)->seconds();
        $this->postJson($url, ['action' => 'claim', 'token' => $second])->assertJsonPath('accepted', true);
        $this->postJson($url, ['action' => 'release', 'token' => $first])->assertJsonPath('accepted', false);
        $this->postJson($url, ['action' => 'release', 'token' => $second])->assertJsonPath('accepted', true);
        $this->postJson($url, ['action' => 'claim', 'token' => $first])->assertJsonPath('accepted', true);
        $this->postJson($url, ['action' => 'acknowledge', 'token' => $first])->assertJsonPath('accepted', true);
        $this->getJson(route('notifications.poll'))->assertJsonPath('student_welcome', null);
        StudentWelcome::record($user);
        $this->getJson(route('notifications.poll'))->assertJsonPath('student_welcome', null);
        $this->assertSame(1, UserNotification::where('user_id', $user->id)->count());
    }

    public function test_existing_students_and_unverified_enrollments_do_not_get_a_guessed_promotion(): void
    {
        $user = $this->makeUserWithRole('student');
        $cashier = $this->makeUserWithRole('cashier');
        $enrollment = Enrollment::factory()->create(['user_id' => $user->id, 'status' => 'approved', 'is_paid' => false]);
        $this->actingAs($cashier)->post(route('cashier.payments.store', $enrollment))->assertRedirect();
        $this->assertDatabaseCount('student_welcomes', 0);
        $this->actingAs($user)->postJson(route('notifications.student-welcome'), ['action' => 'claim', 'token' => 'bad'])->assertUnprocessable();
        $this->getJson(route('notifications.poll'))->assertJsonPath('student_welcome', null);
    }

    public function test_poll_heartbeat_renews_only_the_owned_lease_without_changing_session_activity(): void
    {
        config(['session.lifetime' => 1]);
        $user = $this->makeUserWithRole('student');
        StudentWelcome::record($user);
        $token = (string) Str::uuid();
        $this->actingAs($user)->postJson(route('notifications.student-welcome'), ['action' => 'claim', 'token' => $token])->assertJsonPath('accepted', true);
        $activity = session('last_user_activity_at');
        $this->travel(20)->seconds();
        $this->getJson(route('notifications.poll', ['welcome_token' => $token]))->assertJsonPath('student_welcome.lease_owned', true);
        $this->assertSame($activity, session('last_user_activity_at'));
        $this->travel(20)->seconds();
        $this->getJson(route('notifications.poll', ['welcome_token' => $token]))->assertJsonPath('student_welcome.lease_owned', true);
        $this->postJson(route('notifications.student-welcome'), ['action' => 'claim', 'token' => (string) Str::uuid()])->assertJsonPath('accepted', false);
        $this->getJson(route('notifications.poll', ['welcome_token' => (string) Str::uuid()]))->assertJsonPath('student_welcome.lease_owned', false);
    }

    public function test_promotion_event_and_notification_roll_back_with_the_payment_transaction(): void
    {
        $user = $this->makeUserWithRole('student');
        DB::beginTransaction();
        StudentWelcome::record($user);
        DB::rollBack();
        $this->assertDatabaseMissing('student_welcomes', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('user_notifications', ['user_id' => $user->id, 'event_key' => 'student-promotion:'.$user->id]);
    }
}
