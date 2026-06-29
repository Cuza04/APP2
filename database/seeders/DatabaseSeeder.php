<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Monitoreo',
            'email' => 'monitoreo@example.com',
            'password' => '12345678',
            'password_changed_at' => now(),
        ]);

        User::factory()->create([
            'name' => 'Operador 2',
            'email' => 'user2@example.com',
            'password' => '12345678',
            'role' => UserRole::Operator,
            'password_changed_at' => now(),
        ]);

        $this->call(InspectionLaneSeeder::class);
    }
}
