<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmailVerificationMigrationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_verification_flag_migration_can_reverse_and_reapply_without_changing_existing_identity(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $before = $user->refresh()->getRawOriginal();
        $migration = require database_path('migrations/2026_10_25_140000_add_email_verification_required_to_users.php');
        $migration->down();
        try {
            $this->assertFalse(Schema::hasColumn('users', 'email_verification_required'));
            $remaining = User::findOrFail($user->id)->getRawOriginal();
            foreach (['id', 'email', 'password', 'status', 'email_verified_at'] as $field) {
                $this->assertSame($before[$field], $remaining[$field]);
            }
        } finally {
            $migration->up();
        }
        $this->assertTrue(Schema::hasColumn('users', 'email_verification_required'));
        $this->assertFalse(User::findOrFail($user->id)->email_verification_required);
        $this->assertSame($before['email_verified_at'], User::findOrFail($user->id)->getRawOriginal('email_verified_at'));
    }
}
