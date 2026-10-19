<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsRoles;
use Tests\TestCase;

class StaffProfileSettingsAccessTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_cashier_and_registrar_can_manage_only_their_own_profile_settings(): void
    {
        foreach (['cashier', 'registrar'] as $role) {
            $user = User::factory()->create([
                'name' => ucfirst($role) . ' User',
                'email' => $role . '@example.test',
            ]);
            $user->assignRole($role);

            $this->actingAs($user)
                ->get(route('profile.show'))
                ->assertOk()
                ->assertSee('Profile Settings')
                ->assertSee($user->name)
                ->assertSee($user->email)
                ->assertSee('action="' . route('profile.update') . '"', false)
                ->assertSee('action="' . route('profile.password') . '"', false)
                ->assertSee('href="' . route('profile.show') . '"', false);

            $this->actingAs($user)
                ->put(route('admin.settings.academic.update'), [])
                ->assertForbidden();
        }
    }
}
