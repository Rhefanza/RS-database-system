<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->string('employee_code', 50)->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->enum('assignment_status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE')->index();
            $table->timestamps();
        });

        Schema::create('queue_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_service_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('opened_by_user_id')->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->date('session_date')->index();
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->enum('session_status', ['OPEN', 'CLOSED'])->default('OPEN')->index();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->timestamps();
        });

        Schema::create('queues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('queue_session_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->unsignedInteger('queue_number');
            $table->foreignId('service_desk_id')->nullable()->constrained()->nullOnDelete()->cascadeOnUpdate();
            $table->enum('queue_status', ['WAITING', 'CALLED', 'SERVING', 'COMPLETED', 'CANCELLED'])->default('WAITING')->index();
            $table->dateTime('called_at')->nullable();
            $table->dateTime('service_started_at')->nullable();
            $table->dateTime('service_ended_at')->nullable();
            $table->timestamps();
            $table->unique(['queue_session_id', 'queue_number']);
        });

        Schema::create('queue_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_service_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->dateTime('captured_at')->useCurrent()->index();
            $table->unsignedInteger('waiting_count')->default(0);
            $table->unsignedInteger('serving_count')->default(0);
            $table->unsignedInteger('completed_count')->default(0);
            $table->unsignedInteger('active_desk_count')->default(0);
            $table->unsignedInteger('estimated_wait_minutes')->nullable();
            $table->timestamps();
        });

        Schema::create('saved_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('label', 100);
            $table->string('address', 255)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_locations');
        Schema::dropIfExists('queue_snapshots');
        Schema::dropIfExists('queues');
        Schema::dropIfExists('queue_sessions');
        Schema::dropIfExists('staff_assignments');
    }
};
