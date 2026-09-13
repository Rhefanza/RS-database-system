<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('hospital_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('service_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->unsignedInteger('initial_service_duration')->nullable()->comment('Estimasi durasi dalam menit');
            $table->enum('availability_status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE')->index();
            $table->timestamps();
            $table->unique(['hospital_id', 'service_id']);
        });

        Schema::create('service_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_service_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->enum('day', ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY', 'SUNDAY']);
            $table->time('opens_at');
            $table->time('closes_at');
            $table->unsignedInteger('quota')->nullable();
            $table->enum('schedule_status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE')->index();
            $table->timestamps();
        });

        Schema::create('special_service_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_service_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->date('date');
            $table->time('special_opens_at')->nullable();
            $table->time('special_closes_at')->nullable();
            $table->enum('status', ['OPEN', 'CLOSED', 'CHANGED']);
            $table->string('reason', 255)->nullable();
            $table->timestamps();
            $table->unique(['hospital_service_id', 'date']);
        });

        Schema::create('service_desks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_service_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('name', 100);
            $table->enum('desk_status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE')->index();
            $table->timestamps();
            $table->unique(['hospital_service_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_desks');
        Schema::dropIfExists('special_service_schedules');
        Schema::dropIfExists('service_schedules');
        Schema::dropIfExists('hospital_services');
        Schema::dropIfExists('services');
    }
};
