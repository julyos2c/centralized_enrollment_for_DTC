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

    public function test_staff_dashboards_show_a_working_camera_control_with_staff_profile_routes(): void
    {
        foreach (['admin', 'registrar', 'cashier'] as $role) {
            $user = $this->makeUserWithRole($role);
            $response = $this->actingAs($user)->get(route($role.'.dashboard'))->assertOk()
                ->assertSee($user->name)->assertSee('data-avatar-picker-trigger', false)
                ->assertSee('action="'.route('profile.update').'"', false)
                ->assertSee('href="'.route('profile.show').'"', false)
                ->assertSee('js/dashboard-avatar.js', false)->assertSee('dashboardAvatarCropModal', false);
            $this->assertSame(1, substr_count($response->getContent(), 'id="dashboardAvatarForm"'));
        }
    }

    public function test_staff_camera_upload_returns_to_the_role_dashboard(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        foreach (['admin', 'registrar', 'cashier'] as $role) {
            $user = $this->makeUserWithRole($role);
            $this->actingAs($user)->post(route('profile.update'), [
                'name' => $user->name, 'email' => $user->email, 'return_to_dashboard' => '1',
                'avatar' => \Illuminate\Http\UploadedFile::fake()->image('avatar.jpg'),
            ])->assertSessionHasNoErrors()->assertRedirect(route($role.'.dashboard'));
            \Illuminate\Support\Facades\Storage::disk('public')->assertExists($user->fresh()->avatar);
        }
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
