<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsRoles;
use Tests\TestCase;

class EnrollmentNotificationRoutingTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_enrollment_review_notification_opens_the_current_review_list_and_modal(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $enrollment = Enrollment::factory()->pending()->create();
        $notification = $this->notificationFor(
            $registrar->id,
            route('registrar.enrollments.index', ['review' => $enrollment->id])
        );

        $this->actingAs($registrar)
            ->get(route('notifications.read', $notification))
            ->assertRedirect(route('registrar.enrollments.index', ['review' => $enrollment->id]));

        $this->assertNotNull($notification->fresh()->read_at);

        $this->actingAs($registrar)
            ->get(route('registrar.enrollments.index', ['review' => $enrollment->id]))
            ->assertOk()
            ->assertSee('data-auto-review-url="' . route('registrar.enrollments.show', $enrollment) . '"', false);
    }

    public function test_legacy_enrollment_detail_notification_is_migrated_to_current_review_flow(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $enrollment = Enrollment::factory()->pending()->create();
        $notification = $this->notificationFor($registrar->id, route('registrar.enrollments.show', $enrollment));

        $this->actingAs($registrar)
            ->get(route('notifications.read', $notification))
            ->assertRedirect(route('registrar.enrollments.index', ['review' => $enrollment->id]));
    }

    public function test_stale_and_external_notification_links_fall_back_to_safe_pages(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $completedEnrollment = Enrollment::factory()->create(['status' => 'approved']);
        $stale = $this->notificationFor($registrar->id, route('registrar.enrollments.show', $completedEnrollment));
        $external = $this->notificationFor($registrar->id, 'https://untrusted.example/redirect');

        $this->actingAs($registrar)
            ->get(route('notifications.read', $stale))
            ->assertRedirect(route('registrar.enrollments.index'));

        $this->get(route('notifications.read', $external))
            ->assertRedirect(route('notifications.index'));
    }

    public function test_notification_read_route_cannot_be_used_for_another_users_notification(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $otherRegistrar = $this->makeUserWithRole('registrar');
        $notification = $this->notificationFor($otherRegistrar->id, route('notifications.index'));

        $this->actingAs($registrar)
            ->get(route('notifications.read', $notification))
            ->assertForbidden();
    }

    public function test_registrar_dashboard_only_shows_the_current_users_notifications(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $otherRegistrar = $this->makeUserWithRole('registrar');
        $this->notificationFor($registrar->id, route('notifications.index'))->update(['title' => 'My Registrar Notice']);
        $this->notificationFor($otherRegistrar->id, route('notifications.index'))->update(['title' => 'Other Registrar Notice']);

        $this->actingAs($registrar)
            ->get(route('registrar.dashboard'))
            ->assertOk()
            ->assertSee('My Registrar Notice')
            ->assertDontSee('Other Registrar Notice');
    }

    private function notificationFor(int $userId, string $link): UserNotification
    {
        return UserNotification::create([
            'user_id' => $userId,
            'title' => 'New Enrollment Submitted',
            'message' => 'Enrollment requires review.',
            'type' => 'info',
            'link' => $link,
        ]);
    }
}
