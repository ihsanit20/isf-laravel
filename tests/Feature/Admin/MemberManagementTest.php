<?php

namespace Tests\Feature\Admin;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_member_management_screen(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('admin.members.index'));

        $response->assertOk();
    }

    public function test_admin_can_create_member_profile(): void
    {
        $admin = User::factory()->admin()->create();
        $linkedUser = User::factory()->create();

        $response = $this->actingAs($admin)->post(route('admin.members.store'), [
            'user_id' => $linkedUser->id,
            'name' => 'Member One',
            'phone' => '01700000000',
            'status' => Member::STATUS_ACTIVE,
            'joined_at' => '2026-04-08',
        ]);

        $response->assertRedirect(route('admin.members.index'));

        $this->assertDatabaseHas('members', [
            'user_id' => $linkedUser->id,
            'name' => 'Member One',
            'phone' => '01700000000',
            'status' => Member::STATUS_ACTIVE,
        ]);
    }

    public function test_non_admin_users_cannot_access_member_management(): void
    {
        $memberUser = User::factory()->create();

        $response = $this->actingAs($memberUser)->get(route('admin.members.index'));

        $response->assertForbidden();
    }
}
