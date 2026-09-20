<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kecamatan')) {
            Schema::create('kecamatan', function (Blueprint $table) {
                $table->id('kecamatan_id');
                $table->string('nama_kecamatan')->unique();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('puskesmas', 'kecamatan_id')) {
            Schema::table('puskesmas', function (Blueprint $table) {
                $table->unsignedBigInteger('kecamatan_id')->nullable()->after('puskesmas_id')->index();
                $table->foreign('kecamatan_id')->references('kecamatan_id')->on('kecamatan')->restrictOnDelete()->cascadeOnUpdate();
            });
        }

        if (! Schema::hasTable('dokter')) {
            Schema::create('dokter', function (Blueprint $table) {
                $table->id('dokter_id');
                $table->unsignedBigInteger('puskesmas_layanan_id');
                $table->string('nama_dokter');
                $table->string('spesialisasi')->nullable();
                $table->enum('status', ['AKTIF', 'NONAKTIF'])->default('AKTIF')->index();
                $table->timestamps();

                $table->foreign('puskesmas_layanan_id')->references('puskesmas_layanan_id')->on('puskesmas_layanan')->cascadeOnDelete()->cascadeOnUpdate();
                $table->unique(['puskesmas_layanan_id', 'nama_dokter']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('dokter')) {
            Schema::drop('dokter');
        }

        if (Schema::hasColumn('puskesmas', 'kecamatan_id')) {
            Schema::table('puskesmas', function (Blueprint $table) {
                $table->dropForeign(['kecamatan_id']);
                $table->dropColumn('kecamatan_id');
            });
        }

        if (Schema::hasTable('kecamatan')) {
            Schema::drop('kecamatan');
        }
    }
};
