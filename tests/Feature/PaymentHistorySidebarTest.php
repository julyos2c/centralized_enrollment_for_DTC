<?php

namespace Tests\Feature;

use App\Models\DocumentRequest;
use App\Models\Enrollment;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsRoles;
use Tests\TestCase;

class PaymentHistorySidebarTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_each_role_has_one_payment_history_link_in_its_related_navigation_location(): void
    {
        $cases = [
            ['role' => 'admin', 'url' => route('admin.transactions.index'), 'before' => route('cashier.document-payments.index'), 'after' => route('admin.settings.index')],
            ['role' => 'cashier', 'url' => route('cashier.transactions.index'), 'before' => route('cashier.payments.index'), 'after' => route('cashier.document-payments.index')],
            ['role' => 'registrar', 'url' => route('registrar.transactions.index'), 'before' => route('registrar.documents.index'), 'after' => null],
            ['role' => 'student', 'url' => route('portal.transactions.index'), 'before' => route('portal.enrollment.index'), 'after' => route('portal.appointments.create')],
            ['role' => 'new_applicant', 'url' => route('portal.transactions.index'), 'before' => route('portal.enrollment.index'), 'after' => null],
            ['role' => 'alumni', 'url' => route('portal.transactions.index'), 'before' => route('portal.documents.index'), 'after' => null],
        ];

        foreach ($cases as $case) {
            $user = $this->makeUserWithRole($case['role']);
            $sidebar = $this->sidebarFor($user);
            $this->assertSame(1, substr_count($sidebar, 'Payment History'), $case['role'] . ' should have exactly one history link');
            $this->assertStringContainsString('href="' . $case['url'] . '"', $sidebar, $case['role'] . ' history link should use its authorized route');
            $historyAt = strpos($sidebar, 'href="' . $case['url'] . '"');
            $this->assertLessThan($historyAt, strpos($sidebar, 'href="' . $case['before'] . '"'), $case['role'] . ' link placement before anchor');
            if (in_array($case['role'], ['student', 'new_applicant'], true)) {
                $beforeHistory = substr($sidebar, 0, $historyAt);
                $submenuOpen = strrpos($beforeHistory, '<ul class="nav nav-treeview">');
                $submenuClose = strrpos($beforeHistory, '</ul>');
                $this->assertGreaterThan($submenuOpen, $submenuClose, $case['role'] . ' history link should be outside the Enrollment submenu');
            }
            if ($case['after']) {
                $this->assertLessThan(strpos($sidebar, 'href="' . $case['after'] . '"'), $historyAt, $case['role'] . ' link placement after anchor');
            }
        }
    }

    public function test_history_link_is_active_for_filtered_lists_and_transaction_details_for_all_roles(): void
    {
        $cases = [
            ['role' => 'admin', 'index' => 'admin.transactions.index', 'detail' => 'admin.transactions.show', 'source' => 'enrollment'],
            ['role' => 'cashier', 'index' => 'cashier.transactions.index', 'detail' => 'cashier.transactions.show', 'source' => 'enrollment'],
            ['role' => 'registrar', 'index' => 'registrar.transactions.index', 'detail' => 'registrar.transactions.show', 'source' => 'enrollment'],
            ['role' => 'student', 'index' => 'portal.transactions.index', 'detail' => 'portal.transactions.show', 'source' => 'enrollment'],
            ['role' => 'alumni', 'index' => 'portal.transactions.index', 'detail' => 'portal.transactions.show', 'source' => 'document'],
        ];

        foreach ($cases as $case) {
            $user = $this->makeUserWithRole($case['role']);
            if ($case['source'] === 'enrollment') {
                $enrollment = Enrollment::factory()->create(['user_id' => $user->id]);
                $payment = Payment::factory()->create(['enrollment_id' => $enrollment->id]);
                $key = 'E-' . $payment->id;
            } else {
                $request = DocumentRequest::create(['user_id' => $user->id, 'document_type' => 'tor', 'status' => 'submitted', 'fee' => 100, 'is_paid' => false, 'payment_status' => 'unpaid']);
                $key = 'D-' . $request->id;
            }
            $url = route($case['index']);
            app()->forgetInstance(\JeroenNoten\LaravelAdminLte\AdminLte::class);
            $indexResponse = $this->actingAs($user)->get($url . '?status=verified')->assertOk()->assertSee('Payment History');
            $this->assertLinkActive($indexResponse, $url);
            $detailUrl = route($case['detail'], $key);
            app()->forgetInstance(\JeroenNoten\LaravelAdminLte\AdminLte::class);
            $detailResponse = $this->get($detailUrl)->assertOk();
            $this->assertLinkActive($detailResponse, $url);
        }
    }

    private function sidebarFor($user): string
    {
        app()->forgetInstance(\JeroenNoten\LaravelAdminLte\AdminLte::class);
        $response = $this->actingAs($user)->get(route('notifications.index'))->assertOk();
        $html = $response->getContent();
        $start = strpos($html, '<aside class="main-sidebar');
        $end = strpos($html, '</aside>', $start);
        return substr($html, $start, $end - $start);
    }

    private function assertLinkActive($response, string $url): void
    {
        $html = $response->getContent();
        $start = strpos($html, '<aside class="main-sidebar');
        $end = strpos($html, '</aside>', $start);
        $sidebar = substr($html, $start, $end - $start);
        $this->assertMatchesRegularExpression('~<a\s+class="nav-link[^\"]*\bactive\b[^\"]*"\s+href="' . preg_quote($url, '~') . '"~', $sidebar);
    }
}
