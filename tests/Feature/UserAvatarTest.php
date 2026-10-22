<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Tests\SeedsRoles;
use Tests\TestCase;

class UserAvatarTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    public function test_shared_avatar_renders_photo_with_initials_and_failed_image_fallback(): void
    {
        $user = new User(['name' => 'Maria Reyes', 'avatar' => 'avatars/maria.jpg']);
        $html = Blade::render('<x-dtc.user-avatar :user="$user" class="dtc-review-avatar" />', compact('user'));
        $this->assertStringContainsString(e(Storage::disk('public')->url($user->avatar)), $html);
        $this->assertStringContainsString('MR', $html);
        $this->assertStringContainsString('onerror="this.hidden = true;"', $html);
        $this->assertStringContainsString('dtc-review-avatar', $html);
    }

    public function test_absent_user_or_photo_renders_initials_without_an_image(): void
    {
        $user = new User(['name' => 'Maria Reyes']);
        $html = Blade::render('<x-dtc.user-avatar :user="$user" />', compact('user'));
        $this->assertStringContainsString('MR', $html);
        $this->assertStringNotContainsString('<img', $html);
        $html = Blade::render('<x-dtc.user-avatar name="System" />');
        $this->assertStringContainsString('>S</span>', $html);
    }

    public function test_edit_modal_payload_includes_the_uploaded_avatar_url(): void
    {
        $this->seedRoles();
        $admin = $this->makeUserWithRole('admin');
        $user = User::factory()->create(['avatar' => 'avatars/student.jpg']);
        $this->actingAs($admin)->getJson(route('admin.users.edit', $user))
            ->assertOk()->assertJsonPath('avatar_url', Storage::disk('public')->url($user->avatar));
        $user->update(['avatar' => null]);
        $this->getJson(route('admin.users.edit', $user))->assertOk()->assertJsonPath('avatar_url', null);
    }
}
