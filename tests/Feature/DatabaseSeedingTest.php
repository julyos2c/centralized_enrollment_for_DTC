<?php

namespace Tests\Feature;

use App\Models\CourseSubject;
use App\Models\Program;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\AcademicConfigurationSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\DevelopmentSeeder;
use Database\Seeders\ProgramSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SubjectSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DatabaseSeedingTest extends TestCase
{
    use RefreshDatabase;

    private array $tempFiles = [];

    protected function tearDown(): void
    {
        AcademicConfigurationSeeder::$path = null;
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        putenv('SEED_ADMIN_EMAIL');
        putenv('SEED_ADMIN_PASSWORD');
        parent::tearDown();
    }

    private function runSeeder(string $class): void
    {
        $this->app->make($class)->setContainer($this->app)->__invoke();
    }

    private function asProduction(): void
    {
        $this->app['env'] = 'production';
    }

    private function useAcademicFile(array $data): void
    {
        $file = tempnam(sys_get_temp_dir(), 'acad') . '.json';
        file_put_contents($file, json_encode($data));
        $this->tempFiles[] = $file;
        AcademicConfigurationSeeder::$path = $file;
    }

    private function approved(array $extra): array
    {
        return array_merge(['approved_by' => 'Test Registrar', 'approved_on' => '2026-10-20', 'source' => 'Test fixture'], $extra);
    }

    private function snapshot(): array
    {
        $counts = [];
        foreach (['users', 'roles', 'permissions', 'model_has_roles', 'role_has_permissions', 'settings', 'programs', 'course_subjects', 'applications', 'enrollments', 'payments', 'student_profiles'] as $table) {
            $counts[$table] = DB::table($table)->count();
        }
        return $counts;
    }

    // ── Roles ───────────────────────────────────────────────────────────

    public function test_role_seeder_creates_all_application_roles_once(): void
    {
        $this->runSeeder(RolePermissionSeeder::class);
        $this->runSeeder(RolePermissionSeeder::class);

        $this->assertEqualsCanonicalizing(RolePermissionSeeder::ROLES, Role::pluck('name')->all());
        $this->assertSame(6, Role::where('guard_name', 'web')->count());
        $this->assertSame(0, DB::table('permissions')->count(), 'The app is role-based; no permissions should be created.');
    }

    public function test_role_seeders_never_create_users_or_touch_existing_roles_and_assignments(): void
    {
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'custom_office']);
        $admin = User::factory()->create(['email' => 'superadmin@example.test', 'password' => Hash::make('kept-secret'), 'status' => 'active']);
        $admin->assignRole('admin');
        $before = $admin->fresh()->only(['name', 'email', 'password', 'status']);

        $this->runSeeder(RoleSeeder::class);
        $this->runSeeder(RolePermissionSeeder::class);

        $this->assertSame(1, User::count(), 'Role seeding must not create accounts (no default admin).');
        $this->assertDatabaseMissing('users', ['email' => 'admin@dtc.edu.ph']);
        $this->assertSame($before, $admin->fresh()->only(['name', 'email', 'password', 'status']));
        $this->assertTrue($admin->fresh()->hasRole('admin'));
        $this->assertDatabaseHas('roles', ['name' => 'custom_office']);
    }

    // ── System settings ─────────────────────────────────────────────────

    public function test_settings_seeder_only_inserts_missing_keys_and_never_overwrites(): void
    {
        $existing = Setting::count(); // migration defaults
        Setting::where('key', 'min_password_length')->update(['value' => '12']);
        Setting::where('key', 'items_per_page')->delete();

        $this->runSeeder(SystemSettingsSeeder::class);
        $this->runSeeder(SystemSettingsSeeder::class);

        $this->assertSame('12', Setting::get('min_password_length'), 'Admin-changed values must be kept.');
        $this->assertSame('10', Setting::get('items_per_page'), 'Missing operational defaults are restored.');
        $this->assertSame($existing, Setting::count());
        $this->assertSame(1, Setting::where('key', 'items_per_page')->count());
    }

    public function test_settings_seeder_does_not_invent_institutional_or_financial_values(): void
    {
        Setting::whereIn('key', ['enrollment_fee', 'gcash_number', 'gcash_name', 'enrollment_open'])->delete();

        $this->runSeeder(SystemSettingsSeeder::class);

        foreach (['current_school_year', 'current_semester', 'enrollment_fee', 'gcash_number', 'gcash_name', 'gcash_qr_path',
                  'document_fee_tor', 'appointment_processing_fee', 'registrar_name', 'enrollment_open'] as $key) {
            $this->assertDatabaseMissing('settings', ['key' => $key]);
        }
    }

    // ── Academic configuration ──────────────────────────────────────────

    public function test_academic_seeder_without_an_approved_file_changes_nothing(): void
    {
        AcademicConfigurationSeeder::$path = sys_get_temp_dir() . '/does-not-exist-' . uniqid() . '.json';
        $before = $this->snapshot();

        $this->runSeeder(AcademicConfigurationSeeder::class);

        $this->assertSame($before, $this->snapshot());
        $this->assertNull(Setting::get('current_school_year'));
    }

    public function test_academic_seeder_adds_only_missing_records_and_is_idempotent(): void
    {
        $existing = Program::create(['code' => 'BSIS', 'name' => 'BS Information Systems']);
        CourseSubject::create(['program_id' => $existing->id, 'subject_code' => 'IS101', 'subject_name' => 'Registrar-edited name', 'units' => 3, 'year_level' => 1, 'semester' => 1]);

        $this->useAcademicFile($this->approved([
            'term' => ['school_year' => '2026-2027', 'semester' => '1st'],
            'programs' => [
                ['code' => 'BSIS', 'name' => 'Different Name From File', 'subjects' => [
                    ['subject_code' => 'IS101', 'subject_name' => 'File name', 'units' => 3, 'year_level' => 1, 'semester' => 1],
                    ['subject_code' => 'IS102', 'subject_name' => 'Programming 1', 'units' => 3, 'year_level' => 1, 'semester' => 1],
                ]],
                ['code' => 'NEWPROG', 'name' => 'New Program', 'subjects' => [
                    ['subject_code' => 'NP101', 'subject_name' => 'Intro', 'units' => 1.5, 'year_level' => 1, 'semester' => 2],
                ]],
            ],
        ]));

        $this->runSeeder(AcademicConfigurationSeeder::class);
        $after = $this->snapshot();
        $this->runSeeder(AcademicConfigurationSeeder::class);

        $this->assertSame($after, $this->snapshot(), 'A second run must not change anything.');
        $this->assertSame(2, Program::count());
        $this->assertSame('BS Information Systems', $existing->fresh()->name, 'Existing program names are kept.');
        $this->assertSame('Registrar-edited name', CourseSubject::where('subject_code', 'IS101')->value('subject_name'), 'Existing subjects are not overwritten.');
        $this->assertSame(1, CourseSubject::where('subject_code', 'IS102')->count());
        $this->assertSame('1.5', (string) CourseSubject::where('subject_code', 'NP101')->value('units'));
        $this->assertSame('2026-2027', Setting::get('current_school_year'));
        $this->assertSame('1st', Setting::get('current_semester'));
    }

    public function test_academic_seeder_never_overwrites_a_configured_term(): void
    {
        Setting::updateOrCreate(['key' => 'current_school_year'], ['value' => '2025-2026']);
        Setting::updateOrCreate(['key' => 'current_semester'], ['value' => '2nd']);
        $this->useAcademicFile($this->approved(['term' => ['school_year' => '2026-2027', 'semester' => '1st']]));

        $this->runSeeder(AcademicConfigurationSeeder::class);

        $this->assertSame('2025-2026', Setting::get('current_school_year'));
        $this->assertSame('2nd', Setting::get('current_semester'));
    }

    /** @dataProvider invalidAcademicFiles */
    public function test_invalid_academic_file_aborts_without_writing_anything(array $data): void
    {
        $this->useAcademicFile($data);
        $before = $this->snapshot();

        try {
            $this->runSeeder(AcademicConfigurationSeeder::class);
            $this->fail('Expected the invalid file to be rejected.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('nothing was changed', $e->getMessage());
        }

        $this->assertSame($before, $this->snapshot());
        $this->assertNull(Setting::get('current_school_year'));
    }

    public function invalidAcademicFiles(): array
    {
        $valid = ['code' => 'OK1', 'name' => 'Valid Program', 'subjects' => [['subject_code' => 'A1', 'subject_name' => 'A', 'units' => 3, 'year_level' => 1, 'semester' => 1]]];
        $meta = ['approved_by' => 'R', 'approved_on' => '2026-10-20', 'source' => 'S'];

        return [
            'missing approval metadata' => [['programs' => [$valid]]],
            'bad subject after a valid program' => [$meta + ['programs' => [$valid, ['code' => 'BAD', 'name' => 'Bad', 'subjects' => [['subject_code' => 'B1', 'subject_name' => 'B', 'units' => 0, 'year_level' => 9, 'semester' => 7]]]]]],
            'non-consecutive school year' => [$meta + ['term' => ['school_year' => '2026-2028', 'semester' => '1st'], 'programs' => [$valid]]],
            'unknown semester label' => [$meta + ['term' => ['school_year' => '2026-2027', 'semester' => 'Third']]],
            'duplicate subject code in a program' => [$meta + ['programs' => [['code' => 'DUP', 'name' => 'Dup', 'subjects' => [
                ['subject_code' => 'X1', 'subject_name' => 'X', 'units' => 3, 'year_level' => 1, 'semester' => 1],
                ['subject_code' => 'x1', 'subject_name' => 'X again', 'units' => 3, 'year_level' => 1, 'semester' => 1],
            ]]]]],
        ];
    }

    // ── Development-only data ───────────────────────────────────────────

    /** @dataProvider developmentSeeders */
    public function test_sample_data_seeders_refuse_to_run_in_production(string $class): void
    {
        $this->runSeeder(RolePermissionSeeder::class);
        $this->asProduction();
        $before = $this->snapshot();

        try {
            $this->runSeeder($class);
            $this->fail("$class must refuse to run in production.");
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('only runs in the local or testing environment', $e->getMessage());
        }

        $this->assertSame($before, $this->snapshot());
    }

    public function developmentSeeders(): array
    {
        return [[SubjectSeeder::class], [ProgramSeeder::class], [DemoDataSeeder::class], [DevelopmentSeeder::class]];
    }

    public function test_database_seeder_in_production_adds_no_sample_data_or_accounts_and_is_idempotent(): void
    {
        $this->asProduction();
        AcademicConfigurationSeeder::$path = sys_get_temp_dir() . '/absent-' . uniqid() . '.json';

        $this->runSeeder(DatabaseSeeder::class);
        $first = $this->snapshot();
        $this->runSeeder(DatabaseSeeder::class);

        $this->assertSame($first, $this->snapshot());
        $this->assertSame(0, $first['users']);
        $this->assertSame(0, $first['programs']);
        $this->assertSame(0, $first['course_subjects']);
        $this->assertSame(6, $first['roles']);
    }

    public function test_database_seeder_locally_adds_sample_curriculum_but_no_admin_without_env(): void
    {
        AcademicConfigurationSeeder::$path = sys_get_temp_dir() . '/absent-' . uniqid() . '.json';

        $this->runSeeder(DatabaseSeeder::class);
        $subjects = CourseSubject::count();
        $this->runSeeder(DatabaseSeeder::class);

        $this->assertGreaterThan(0, $subjects);
        $this->assertSame($subjects, CourseSubject::count(), 'Sample curriculum seeding is idempotent.');
        $this->assertSame(0, User::count());
    }

    public function test_development_admin_comes_from_env_and_existing_account_is_never_modified(): void
    {
        $this->runSeeder(RolePermissionSeeder::class);
        putenv('SEED_ADMIN_EMAIL=local-admin@example.test');
        putenv('SEED_ADMIN_PASSWORD=Local-Only-Pass-123');

        $this->runSeeder(DevelopmentSeeder::class);
        $admin = User::where('email', 'local-admin@example.test')->first();
        $this->assertNotNull($admin);
        $this->assertTrue($admin->hasRole('admin'));
        $this->assertTrue(Hash::check('Local-Only-Pass-123', $admin->password));

        $admin->update(['password' => Hash::make('changed-by-admin'), 'status' => 'locked']);
        putenv('SEED_ADMIN_PASSWORD=Something-Else-456');
        $this->runSeeder(DevelopmentSeeder::class);

        $admin->refresh();
        $this->assertSame(1, User::where('email', 'local-admin@example.test')->count());
        $this->assertTrue(Hash::check('changed-by-admin', $admin->password));
        $this->assertSame('locked', $admin->status);
    }
}
