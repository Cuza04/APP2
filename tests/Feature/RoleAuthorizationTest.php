<?php

namespace Tests\Feature;

use App\Enums\LaneStatus;
use App\Enums\UserRole;
use App\Filament\Resources\InspectionLaneResource;
use App\Filament\Resources\UserResource;
use App\Models\InspectionLane;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesInspectionFixtures;
use Tests\TestCase;

class RoleAuthorizationTest extends TestCase
{
    use CreatesInspectionFixtures;
    use RefreshDatabase;

    public function test_operator_cannot_access_user_management(): void
    {
        $operator = User::factory()->create();

        $this->actingAs($operator);

        $this->assertFalse(UserResource::canViewAny());
        $this->assertFalse(UserResource::canCreate());
    }

    public function test_admin_can_access_user_management(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        $this->assertTrue(UserResource::canViewAny());
        $this->assertTrue(UserResource::canCreate());
    }

    public function test_operator_cannot_deactivate_users(): void
    {
        $admin = User::factory()->admin()->create();
        $operator = User::factory()->create(['email' => 'user2@example.com']);

        $this->actingAs($operator);

        UserResource::setActiveStatus($admin, false);

        $admin->refresh();

        $this->assertTrue($admin->is_active);
    }

    public function test_admin_can_deactivate_operator(): void
    {
        $admin = User::factory()->admin()->create();
        $operator = User::factory()->create(['email' => 'user2@example.com']);

        $this->actingAs($admin);

        UserResource::setActiveStatus($operator, false);

        $operator->refresh();

        $this->assertFalse($operator->is_active);
    }

    public function test_operator_cannot_change_lane_status(): void
    {
        $operator = User::factory()->create();
        $lane = $this->createOpenEntryLane('C9');

        $this->actingAs($operator);

        InspectionLaneResource::updateLaneStatus($lane, LaneStatus::Closed);

        $lane->refresh();

        $this->assertSame(LaneStatus::Open, $lane->status);
    }

    public function test_admin_can_change_lane_status(): void
    {
        $admin = User::factory()->admin()->create();
        $lane = InspectionLane::query()->first() ?? $this->createOpenEntryLane('C8');

        $this->actingAs($admin);

        InspectionLaneResource::updateLaneStatus($lane, LaneStatus::Closed);

        $lane->refresh();

        $this->assertSame(LaneStatus::Closed, $lane->status);
    }

    public function test_operator_cannot_edit_lanes(): void
    {
        $operator = User::factory()->create();
        $lane = $this->createOpenEntryLane('C7');

        $this->actingAs($operator);

        $this->assertFalse(InspectionLaneResource::canEdit($lane));
        $this->assertFalse(InspectionLaneResource::canCreate());
    }

    public function test_operator_can_still_access_panel(): void
    {
        $operator = User::factory()->create([
            'email' => 'user2@example.com',
            'role' => UserRole::Operator,
        ]);

        $this->assertTrue($operator->canAccessPanel(filament()->getCurrentPanel()));
        $this->assertFalse($operator->isAdmin());
    }

    public function test_monitoreo_user_is_admin_by_default_after_seed(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'monitoreo@example.com')->first();
        $operator = User::query()->where('email', 'user2@example.com')->first();

        $this->assertNotNull($admin);
        $this->assertTrue($admin->isAdmin());
        $this->assertNotNull($operator);
        $this->assertFalse($operator->isAdmin());
    }
}
