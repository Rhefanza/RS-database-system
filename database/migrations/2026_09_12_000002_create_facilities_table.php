<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facilities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
        });

        Schema::create('hospital_facilities', function (Blueprint $table) {
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->primary(['hospital_id', 'facility_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospital_facilities');
        Schema::dropIfExists('facilities');
    }
};
