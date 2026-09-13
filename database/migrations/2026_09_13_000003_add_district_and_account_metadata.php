<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });

        Schema::table('hospitals', function (Blueprint $table) {
            $table->foreignId('district_id')->nullable()->after('id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->enum('data_status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE')->after('is_emergency')->index();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('email');
            $table->enum('role', ['ADMIN', 'OFFICER', 'PUBLIC'])->default('ADMIN')->after('password')->index();
            $table->enum('account_status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE')->after('role')->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'role', 'account_status']);
        });

        Schema::table('hospitals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('district_id');
            $table->dropColumn('data_status');
        });

        Schema::dropIfExists('districts');
    }
};
