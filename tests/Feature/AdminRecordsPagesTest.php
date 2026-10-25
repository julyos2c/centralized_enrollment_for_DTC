<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BackupLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsRoles;
use Tests\TestCase;

class AdminRecordsPagesTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_audit_page_keeps_filtering_pagination_and_escaped_record_details(): void
    {
        $admin = $this->makeUserWithRole('admin');
        AuditLog::create(['user_id' => $admin->id, 'action' => 'document.checked', 'description' => '<script>unexpected()</script>']);
        $this->actingAs($admin)->get(route('admin.settings.audit', ['action' => 'document.checked', 'per_page' => 5]))
            ->assertOk()->assertViewHas('logs', fn ($logs) => $logs->perPage() === 5 && $logs->total() === 1)
            ->assertSee('data-label="Description"', false)->assertSee('id="audit-per-page"', false)
            ->assertSee('&lt;script&gt;unexpected()&lt;/script&gt;', false)->assertDontSee('<script>unexpected()</script>', false)
            ->assertDontSee('dtc-table-rows-options', false);
    }

    public function test_backup_history_has_distinct_confirmation_targets_and_named_actions(): void
    {
        $admin = $this->makeUserWithRole('admin');
        foreach (['completed', 'failed'] as $status) {
            $backups[] = BackupLog::create(['filename' => 'backup_'.$status.'.sql', 'size' => 1024, 'status' => $status, 'created_by' => $admin->id]);
        }
        $response = $this->actingAs($admin)->get(route('admin.settings.backup'))->assertOk();
        foreach ($backups as $backup) {
            $response->assertSee('id="del-backup-'.$backup->id.'"', false)
                ->assertSee('data-dtc-confirm-form="#del-backup-'.$backup->id.'"', false)
                ->assertSee('aria-label="Delete '.$backup->filename.'"', false);
        }
        $response->assertSee(route('admin.settings.backup.download', $backups[0]), false)
            ->assertDontSee(route('admin.settings.backup.download', $backups[1]), false)
            ->assertSee('Uploaded SQL files are not restored')->assertDontSee('id="sql-file"', false);
    }
}
