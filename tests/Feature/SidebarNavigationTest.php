<?php

namespace Tests\Feature;

use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsRoles;
use Tests\TestCase;

class SidebarNavigationTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    /** @dataProvider unreadCounts */
    public function test_sidebar_icon_structure_and_unread_counts(int $count, string $display): void
    {
        $this->seedRoles();
        $user = $this->makeUserWithRole('student');
        for ($i = 0; $i < $count; $i++) {
            UserNotification::create(['user_id' => $user->id, 'title' => 'Update', 'message' => 'Test notification', 'type' => 'info']);
        }
        $response = $this->actingAs($user)->get(route('notifications.index'))->assertOk();
        $document = new \DOMDocument();
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $links = $xpath->query('//aside//ul[contains(@class,"nav-sidebar")]//a[contains(@class,"nav-link")]');
        foreach ($links as $link) {
            $this->assertSame(1, $xpath->query('./span[@class="dtc-sidebar-icon-slot"]', $link)->length);
            $this->assertSame(1, $xpath->query('./p/span[@class="dtc-sidebar-menu-label"]', $link)->length);
        }
        $parents = $xpath->query('//aside//li[contains(@class,"has-treeview")]/a');
        $this->assertGreaterThan(0, $parents->length);
        foreach ($parents as $parent) {
            $this->assertSame('button', $parent->getAttribute('role'));
            $this->assertContains($parent->getAttribute('aria-expanded'), ['true', 'false']);
        }
        $this->assertSame(0, $xpath->query('//aside//li[contains(@class,"has-treeview")]/a//i[@data-lucide="chevron-left"]')->length);
        $this->assertSame(0, $xpath->query('//aside//button[@id="dtcSidebarProfileToggle"]//span[contains(@class,"dtc-sidebar-profile-chevron")]')->length);
        $this->assertSame(0, $xpath->query('//aside//p[contains(@class,"dtc-sidebar-menu-copy")]/span[@data-sidebar-unread]')->length);
        $badge = $xpath->query('//aside//span[@class="dtc-sidebar-icon-slot"]/span[@data-sidebar-unread]')->item(0);
        $this->assertNotNull($badge);
        $this->assertSame($display, trim($badge->textContent));
        $this->assertSame($count === 0, $badge->hasAttribute('hidden'));
        $this->assertSame($count.' unread notifications', $badge->getAttribute('aria-label'));
    }

    public static function unreadCounts(): array
    {
        return [[0, '0'], [4, '4'], [24, '24'], [120, '99+']];
    }
}
