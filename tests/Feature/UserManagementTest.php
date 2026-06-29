<?php

namespace Tests\Feature;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_user_cannot_access_panel(): void
    {
        $user = User::factory()->inactive()->create();

        $this->assertFalse($user->canAccessPanel(filament()->getCurrentPanel()));
    }

    public function test_active_user_can_access_panel(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->assertTrue($user->canAccessPanel(filament()->getCurrentPanel()));
    }

    public function test_user_can_be_deactivated(): void
    {
        $admin = User::factory()->admin()->create();
        $operator = User::factory()->create(['email' => 'operador@terminal.com']);

        $this->actingAs($admin);

        UserResource::setActiveStatus($operator, false);

        $operator->refresh();

        $this->assertFalse($operator->is_active);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'action' => 'user_deactivated',
            'subject_type' => $operator->getMorphClass(),
            'subject_id' => $operator->id,
        ]);
    }

    public function test_user_cannot_deactivate_themselves(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        UserResource::setActiveStatus($admin, false);

        $admin->refresh();

        $this->assertTrue($admin->is_active);
    }

    public function test_last_active_user_cannot_be_deactivated(): void
    {
        $onlyUser = User::factory()->admin()->create();

        $this->actingAs($onlyUser);

        UserResource::setActiveStatus($onlyUser, false);

        $onlyUser->refresh();

        $this->assertTrue($onlyUser->is_active);
    }

    public function test_deactivated_user_can_be_reactivated(): void
    {
        $admin = User::factory()->admin()->create();
        $operator = User::factory()->inactive()->create();

        $this->actingAs($admin);

        UserResource::setActiveStatus($operator, true);

        $operator->refresh();

        $this->assertTrue($operator->is_active);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'action' => 'user_activated',
        ]);
    }

    public function test_new_user_must_change_password(): void
    {
        $user = User::factory()->create([
            'password_changed_at' => null,
        ]);

        $this->assertTrue($user->mustChangePassword());
    }

    public function test_admin_password_reset_forces_change_on_next_login(): void
    {
        $user = User::factory()->create([
            'password_changed_at' => now(),
        ]);

        $user->update([
            'password' => 'nueva-clave-temporal',
            'password_changed_at' => null,
        ]);

        $user->refresh();

        $this->assertTrue($user->mustChangePassword());
    }
}
