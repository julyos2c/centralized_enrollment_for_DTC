<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\TablePagination;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsRoles;
use Tests\TestCase;

class TablePaginationTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    public function test_dashboards_remain_fixed_summaries_without_row_limit_controls(): void
    {
        $this->seedRoles();
        \App\Models\Enrollment::factory()->count(7)->create();
        \App\Models\Payment::factory()->count(7)->create();
        $registrar = $this->makeUserWithRole('registrar');
        $this->actingAs($registrar)->get(route('registrar.dashboard', ['per_page' => 50, 'page' => 2]))
            ->assertOk()->assertDontSee('data-table-rows-control', false)
            ->assertViewHas('recentEnrollments', fn ($rows) => $rows instanceof \Illuminate\Support\Collection && $rows->count() === 5);
        $cashier = $this->makeUserWithRole('cashier');
        $this->actingAs($cashier)->get(route('cashier.dashboard', ['per_page' => 50, 'recent_page' => 2]))
            ->assertOk()->assertDontSee('data-table-rows-control', false)
            ->assertViewHas('recentPayments', fn ($rows) => $rows instanceof \Illuminate\Support\Collection && $rows->count() === 5);
    }

    public function test_row_limit_accepts_only_supported_values(): void
    {
        foreach ([5, 10, 15, 20, 25, 50] as $limit) {
            request()->query->set('per_page', (string) $limit);
            $this->assertSame($limit, TablePagination::perPage(15));
        }
        foreach ([null, '', 'bad', '-5', '0', '50000', ['5']] as $invalid) {
            request()->query->set('per_page', $invalid);
            $this->assertSame(15, TablePagination::perPage(15));
        }
    }

    public function test_row_limits_are_available_across_account_roles(): void
    {
        $this->seedRoles();
        foreach ([
            ['admin', 'admin.users.index', 'users'],
            ['registrar', 'registrar.applications.index', 'applications'],
            ['cashier', 'cashier.payments.index', 'enrollments'],
            ['student', 'portal.transactions.index', 'transactions'],
            ['alumni', 'portal.transactions.index', 'transactions'],
            ['new_applicant', 'portal.transactions.index', 'transactions'],
        ] as [$role, $route, $variable]) {
            $user = $this->makeUserWithRole($role);
            foreach ([5, 10] as $limit) {
                $this->actingAs($user)->get(route($route, ['per_page' => $limit]))
                    ->assertOk()
                    ->assertViewHas($variable, fn ($records) => $records->perPage() === $limit);
            }
        }
    }

    public function test_user_rows_are_limited_and_pagination_preserves_search_and_limit(): void
    {
        $this->seedRoles();
        $admin = $this->makeUserWithRole('admin');
        User::factory()->count(12)->create(['name' => 'Row Limit Person']);
        $this->actingAs($admin)->get(route('admin.users.index', ['per_page' => 5, 'search' => 'Row Limit Person']))
            ->assertOk()->assertViewHas('users', fn ($users) => $users->count() === 5 && $users->total() === 12)
            ->assertSee('per_page=5', false)->assertSee('search=Row', false);
    }
}
