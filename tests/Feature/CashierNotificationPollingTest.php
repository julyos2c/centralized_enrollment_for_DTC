<?php

namespace Tests\Feature;

use App\Models\UserNotification;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsRoles;
use Tests\TestCase;

class CashierNotificationPollingTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_open_cashier_dashboard_can_poll_and_receive_new_notifications_without_reloading_the_page(): void
    {
        $cashier = $this->makeUserWithRole('cashier');
        $this->actingAs($cashier)->get(route('cashier.dashboard'))
            ->assertOk()
            ->assertSee('cashier-notification-polling.js')
            ->assertSee(route('cashier.notifications.poll'), false);

        $notification = NotificationService::sendForEvent(
            $cashier,
            'enrollment-gcash-payment-pending:42',
            'New GCash Payment Pending Verification',
            'GCash payment is ready for verification.',
            'info',
            route('cashier.payments.show', 42)
        );

        $response = $this->actingAs($cashier)->getJson(route('cashier.notifications.poll', ['after_id' => 0]));
        $response->assertOk()
            ->assertJsonPath('notifications.0.id', $notification->id)
            ->assertJsonPath('notifications.0.title', 'New GCash Payment Pending Verification')
            ->assertJsonPath('notifications.0.read_url', route('notifications.read', $notification))
            ->assertJsonPath('unread_count', 1)
            ->assertJsonStructure(['notifications' => [['id', 'title', 'message', 'type', 'is_read', 'relative_time', 'read_url']]]);

        // A missed response or reconnect replays the same durable row; the UI
        // deduplicates by id and the database still contains one event record.
        $this->getJson(route('cashier.notifications.poll', ['after_id' => 0]))
            ->assertJsonPath('notifications.0.id', $notification->id);
        $this->assertSame(1, UserNotification::where('user_id', $cashier->id)->count());

        // A separate Cashier session has its own authenticated cursor and can
        // receive the same payment event without sharing another user's data.
        $secondCashier = $this->makeUserWithRole('cashier');
        NotificationService::sendForEvent(
            $secondCashier,
            'enrollment-gcash-payment-pending:42',
            'New GCash Payment Pending Verification',
            'GCash payment is ready for verification.',
            'info',
            route('cashier.payments.show', 42)
        );
        $this->actingAs($secondCashier)->getJson(route('cashier.notifications.poll', ['after_id' => 0]))
            ->assertOk()
            ->assertJsonCount(1, 'notifications');
    }

    public function test_polling_is_cashier_only_and_never_returns_another_users_notifications(): void
    {
        $cashier = $this->makeUserWithRole('cashier');
        $otherCashier = $this->makeUserWithRole('cashier');
        $student = $this->makeUserWithRole('student');
        $admin = $this->makeUserWithRole('admin');

        $own = NotificationService::sendForEvent($cashier, 'own-event', 'Cashier notice', 'Own message', 'info', '/cashier/payments');
        NotificationService::sendForEvent($otherCashier, 'private-event', 'Private notice', 'Other message', 'info', '/cashier/payments');

        $this->actingAs($cashier)->getJson(route('cashier.notifications.poll', ['after_id' => 0]))
            ->assertOk()
            ->assertJsonCount(1, 'notifications')
            ->assertJsonPath('notifications.0.id', $own->id)
            ->assertJsonMissing(['message' => 'Other message']);

        $this->actingAs($student)->getJson(route('cashier.notifications.poll'))->assertForbidden();
        $this->actingAs($admin)->getJson(route('cashier.notifications.poll'))->assertForbidden();
    }

    public function test_retried_event_creation_is_idempotent_for_each_cashier(): void
    {
        $cashier = $this->makeUserWithRole('cashier');
        $attributes = [$cashier, 'enrollment-gcash-payment-pending:77', 'New GCash Payment Pending Verification', 'Awaiting review.', 'info', route('cashier.payments.show', 77)];

        $first = NotificationService::sendForEvent(...$attributes);
        $retry = NotificationService::sendForEvent(...$attributes);

        $this->assertSame($first->id, $retry->id);
        $this->assertSame(1, UserNotification::where('user_id', $cashier->id)->count());
    }

    public function test_notification_polling_rejects_an_invalid_cursor(): void
    {
        $cashier = $this->makeUserWithRole('cashier');

        $this->actingAs($cashier)->getJson(route('cashier.notifications.poll', ['after_id' => -1]))
            ->assertUnprocessable();
    }
}
