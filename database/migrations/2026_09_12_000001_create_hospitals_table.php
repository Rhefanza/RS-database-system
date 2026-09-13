<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospitals', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('code', 20)->unique();
            $table->enum('class', ['A', 'B', 'C', 'D'])->index();
            $table->enum('ownership', ['Pemerintah', 'BUMN', 'Swasta']);
            $table->string('phone', 30);
            $table->string('emergency_phone', 30)->nullable();
            $table->text('address');
            $table->string('city', 100)->index();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->text('description')->nullable();
            $table->boolean('is_emergency')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospitals');
    }
};
