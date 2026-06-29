<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspection_lanes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('lane_type');
            $table->string('direction');
            $table->string('status')->default('open');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('inspection_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lane_id')->constrained('inspection_lanes')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->dateTime('hour_slot');
            $table->string('status')->default('pending');
            $table->foreignId('superseded_by_id')->nullable()->constrained('inspection_assignments')->nullOnDelete();
            $table->text('regeneration_reason')->nullable();
            $table->foreignId('regenerated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('regenerated_at')->nullable();
            $table->timestamps();

            $table->index(['hour_slot', 'status']);
        });

        Schema::create('inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->unique()->constrained('inspection_assignments')->cascadeOnDelete();
            $table->foreignId('lane_id')->constrained('inspection_lanes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('plate');
            $table->string('result');
            $table->text('comments')->nullable();
            $table->dateTime('inspected_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('action');
            $table->nullableMorphs('subject');
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('inspections');
        Schema::dropIfExists('inspection_assignments');
        Schema::dropIfExists('inspection_lanes');
    }
};
