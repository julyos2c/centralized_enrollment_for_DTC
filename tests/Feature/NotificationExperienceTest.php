<?php

namespace Tests\Feature;

use App\Models\UserNotification;
use App\Models\Enrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsRoles;
use Tests\TestCase;

class NotificationExperienceTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function notice($user, array $attributes = []): UserNotification
    {
        return UserNotification::create(array_merge(['user_id' => $user->id, 'title' => 'Document ready', 'message' => 'Bring your ID.', 'type' => 'info', 'link' => route('notifications.index')], $attributes));
    }

    public function test_dropdown_shows_latest_five_without_marking_them_read_for_every_role(): void
    {
        foreach (['admin', 'registrar', 'cashier', 'student', 'alumni', 'new_applicant'] as $role) {
            $user = $this->makeUserWithRole($role);
            $old = $this->notice($user, ['title' => 'Old '.$role.' update']);
            for ($i = 0; $i < 5; $i++) $latest = $this->notice($user, ['title' => $role.' update '.$i]);
            $response = $this->actingAs($user)->get(route('notifications.index'))->assertOk();
            $dom = new \DOMDocument();
            $previous = libxml_use_internal_errors(true);
            $dom->loadHTML($response->getContent()); libxml_clear_errors(); libxml_use_internal_errors($previous);
            $xpath = new \DOMXPath($dom);
            $this->assertSame(5, $xpath->query('//div[@id="notify-list"]/div[@data-notification-id]')->length);
            $this->assertSame((string) $latest->id, $xpath->query('//div[@id="notify-list"]/div[@data-notification-id]')->item(0)->getAttribute('data-notification-id'));
            $this->assertNull($old->fresh()->read_at);
            $this->assertSame(6, UserNotification::where('user_id', $user->id)->whereNull('read_at')->count());
        }
    }

    public function test_search_type_and_read_filters_run_before_pagination_and_remain_private(): void
    {
        $user = $this->makeUserWithRole('student');
        $other = $this->makeUserWithRole('student');
        $target = $this->notice($user, ['title' => 'GCash appointment payment', 'message' => 'Unique reference', 'read_at' => now()]);
        $this->notice($other, ['title' => 'GCash appointment payment', 'message' => 'Unique reference', 'read_at' => now()]);
        for ($i = 0; $i < 12; $i++) $this->notice($user);
        $this->actingAs($user)->get(route('notifications.index', ['search' => 'Unique', 'category' => 'payment', 'state' => 'read', 'per_page' => 5]))
            ->assertOk()->assertViewHas('notifications', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $target->id && $rows->perPage() === 5)
            ->assertViewHas('totalCount', 13)->assertViewHas('unreadCount', 12);
        $this->get(route('notifications.index', ['category' => 'appointment']))->assertViewHas('notifications', fn ($rows) => $rows->total() === 0);
        $this->assertSame('payment', $target->category);
        $this->getJson(route('notifications.index', ['category' => 'invalid']))->assertUnprocessable();
        $this->get(route('notifications.index', ['search' => "' OR 1=1 --"]))->assertViewHas('notifications', fn ($rows) => $rows->total() === 0);
    }

    public function test_open_is_nonmutating_and_state_changes_are_idempotent_and_owner_only(): void
    {
        $user = $this->makeUserWithRole('student');
        $notice = $this->notice($user, ['link' => route('portal.dashboard')]);
        $this->actingAs($user)->get(route('notifications.open', $notice))->assertRedirect(route('portal.dashboard'));
        $this->assertNull($notice->fresh()->read_at);
        $url = route('notifications.state', $notice);
        $this->postJson($url, ['read' => true])->assertOk()->assertJsonPath('read', true)->assertJsonPath('unread_count', 0);
        $stamp = $notice->fresh()->read_at;
        $this->postJson($url, ['read' => true])->assertOk();
        $this->assertTrue($stamp->equalTo($notice->fresh()->read_at));
        $this->postJson($url, ['read' => false])->assertOk()->assertJsonPath('unread_count', 1);
        $this->postJson($url, ['read' => 'invalid'])->assertUnprocessable();
        $this->actingAs($this->makeUserWithRole('student'))->postJson($url, ['read' => true])->assertForbidden();
        $this->get(route('notifications.open', $notice))->assertForbidden();
        $this->deleteJson(route('notifications.destroy', $notice))->assertForbidden();
    }

    public function test_mark_all_and_delete_read_do_not_touch_other_users_or_unread_history(): void
    {
        $user = $this->makeUserWithRole('alumni');
        $other = $this->makeUserWithRole('alumni');
        $read = $this->notice($user);
        $foreign = $this->notice($other);
        $this->actingAs($user)->postJson(route('notifications.markAllRead'))->assertOk()->assertJsonPath('unread_count', 0);
        $unread = $this->notice($user);
        $this->deleteJson(route('notifications.destroyRead'))->assertOk()->assertJsonPath('unread_count', 1);
        $this->assertDatabaseMissing('user_notifications', ['id' => $read->id]);
        $this->assertDatabaseHas('user_notifications', ['id' => $unread->id, 'read_at' => null]);
        $this->assertNull($foreign->fresh()->read_at);
    }

    public function test_unavailable_external_and_unauthorized_destinations_fall_back(): void
    {
        $user = $this->makeUserWithRole('student');
        $foreignEnrollment = Enrollment::factory()->create();
        foreach (['https://untrusted.example/', '/not-a-real-route', route('registrar.documents.index'), route('portal.enrollment.payment-info', 999999), route('portal.enrollment.payment-info', $foreignEnrollment)] as $link) {
            $notice = $this->notice($user, ['link' => $link]);
            $this->actingAs($user)->get(route('notifications.open', $notice))->assertRedirect(route('notifications.index'))->assertSessionHas('info');
            $this->assertNull($notice->fresh()->read_at);
        }
    }

    public function test_page_recovers_after_deleting_last_result_and_preserves_filters(): void
    {
        $user = $this->makeUserWithRole('student');
        for ($i = 0; $i < 6; $i++) $this->notice($user);
        $response = $this->actingAs($user)->get(route('notifications.index', ['per_page' => 5, 'page' => 2, 'category' => 'document', 'state' => 'unread']))->assertOk();
        $response->assertViewHas('notifications', fn ($rows) => $rows->total() === 6 && $rows->count() === 1 && str_contains($rows->url(1), 'category=document'));
        UserNotification::where('user_id', $user->id)->delete();
        $response = $this->get(route('notifications.index', ['per_page' => 5, 'page' => 2, 'category' => 'document', 'state' => 'unread']))->assertRedirect();
        $this->assertStringContainsString('page=1', $response->headers->get('Location'));
        $this->assertStringContainsString('category=document', $response->headers->get('Location'));
    }

    public function test_new_read_state_endpoint_rejects_stale_csrf_and_guests(): void
    {
        $this->app->bind(\App\Http\Middleware\VerifyCsrfToken::class, function ($app) {
            return new class($app, $app['encrypter']) extends \App\Http\Middleware\VerifyCsrfToken {
                protected function runningUnitTests() { return false; }
            };
        });
        $user = $this->makeUserWithRole('student');
        $notification = $this->notice($user);
        $this->actingAs($user)->withSession(['_token' => 'fresh-token'])
            ->postJson(route('notifications.state', $notification), ['read' => true, '_token' => 'expired-token'])->assertStatus(419);
        $this->assertNull($notification->fresh()->read_at);
        $this->withSession(['_token' => 'fresh-token'])->postJson(route('notifications.state', $notification), ['read' => true, '_token' => 'fresh-token'])->assertOk();
        auth()->logout();
        $this->getJson(route('notifications.index'))->assertUnauthorized();
    }

    public function test_old_receipt_notifications_open_current_modal_for_staff_only(): void
    {
        $enrollment = Enrollment::factory()->create(['status' => 'approved', 'is_paid' => true]);
        \App\Models\Payment::factory()->create(['enrollment_id' => $enrollment->id, 'status' => 'verified']);
        foreach (['cashier', 'admin'] as $role) {
            $staff = $this->makeUserWithRole($role);
            $notification = $this->notice($staff, ['link' => route('cashier.payments.receipt', $enrollment)]);
            $destination = route('cashier.payments.index', ['receipt' => $enrollment->id]);
            $this->actingAs($staff)->get(route('notifications.open', $notification))->assertRedirect($destination);
            $this->get(route('notifications.read', $notification))->assertRedirect($destination);
            $this->get($destination)->assertOk()->assertSee('data-auto-receipt', false)
                ->assertSee('id="receiptModal"', false)->assertViewHas('receiptEnrollment', fn ($record) => $record->id === $enrollment->id);
        }
        $student = $this->makeUserWithRole('student');
        $notification = $this->notice($student, ['link' => route('cashier.payments.receipt', $enrollment)]);
        $this->actingAs($student)->get(route('notifications.open', $notification))->assertRedirect(route('notifications.index'));
        $staff = $this->makeUserWithRole('cashier');
        $missing = $this->notice($staff, ['link' => route('cashier.payments.receipt', 999999)]);
        $this->actingAs($staff)->get(route('notifications.open', $missing))->assertRedirect(route('cashier.payments.index'));
        $this->get(route('cashier.payments.index', ['receipt' => 999999]))->assertOk()->assertDontSee('data-auto-receipt data-url=', false);
    }

    public function test_every_role_can_poll_only_their_own_notifications_without_marking_read(): void
    {
        foreach (['admin', 'registrar', 'cashier', 'student', 'alumni', 'new_applicant'] as $role) {
            $user = $this->makeUserWithRole($role);
            $own = $this->notice($user);
            $this->actingAs($user)->getJson(route('notifications.poll', ['after_id' => 0]))
                ->assertOk()->assertJsonCount(1, 'notifications')
                ->assertJsonPath('notifications.0.id', $own->id)
                ->assertJsonPath('notifications.0.open_url', route('notifications.open', $own))
                ->assertJsonPath('unread_count', 1)->assertJsonPath('total_count', 1)->assertJsonPath('read_count', 0);
            $this->getJson(route('notifications.poll', ['after_id' => $own->id]))->assertJsonCount(0, 'notifications');
            $this->postJson(route('notifications.state', $own), ['read' => true])->assertOk();
            $this->getJson(route('notifications.poll', ['after_id' => $own->id]))
                ->assertJsonCount(0, 'notifications')->assertJsonCount(1, 'recent_notifications')
                ->assertJsonPath('recent_notifications.0.id', $own->id)->assertJsonPath('recent_notifications.0.is_read', true)
                ->assertJsonPath('unread_count', 0);
            $this->postJson(route('notifications.state', $own), ['read' => false])->assertOk();
            $this->assertNull($own->fresh()->read_at);
            $this->get(route('notifications.index'))->assertOk()->assertSee('cashier-notification-polling.js');
        }
        $this->getJson(route('notifications.poll', ['after_id' => -1]))->assertUnprocessable();
        auth()->logout();
        $this->getJson(route('notifications.poll'))->assertUnauthorized();
    }

    public function test_payment_notifications_open_owned_payment_page_and_legacy_links_redirect(): void
    {
        $user = $this->makeUserWithRole('student');
        $enrollment = Enrollment::factory()->create(['user_id' => $user->id, 'status' => 'approved']);
        $notice = $this->notice($user, ['link' => route('portal.enrollment.payment-info', $enrollment)]);
        $url = route('portal.enrollment.payment-info', $enrollment);
        $this->actingAs($user)->get(route('notifications.open', $notice))->assertRedirect($url);
        $this->get(route('notifications.read', $notice))->assertRedirect($url);
        $this->get($url)->assertOk()->assertViewIs('student.enrollment.payment');
        $this->get(route('portal.enrollment.index', ['payment' => $enrollment->id]))->assertRedirect($url);
        $other = $this->makeUserWithRole('student');
        $foreign = $this->notice($other, ['link' => route('portal.enrollment.payment-info', $enrollment)]);
        $this->actingAs($other)->get(route('notifications.open', $foreign))->assertRedirect(route('notifications.index'));
        $this->get($url)->assertForbidden();
    }

    public function test_payment_endpoint_returns_the_same_full_page_for_normal_and_ajax_requests_securely(): void
    {
        foreach (['new_applicant', 'student', 'alumni'] as $role) {
            $user = $this->makeUserWithRole($role);
            $enrollment = Enrollment::factory()->create(['user_id' => $user->id, 'status' => 'approved']);
            $url = route('portal.enrollment.payment-info', $enrollment);
            $this->actingAs($user)->get($url)->assertOk()->assertViewIs('student.enrollment.payment');
            $this->get($url, ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()
                ->assertViewIs('student.enrollment.payment')->assertSee('id="payment-content"', false);
            $this->actingAs($this->makeUserWithRole($role))->get($url)->assertForbidden();
            $this->get($url, ['X-Requested-With' => 'XMLHttpRequest'])->assertForbidden();
        }
    }
}
