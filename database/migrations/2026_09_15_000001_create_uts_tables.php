<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kecamatan', function (Blueprint $table) {
            $table->id('kecamatan_id');
            $table->string('nama_kecamatan')->unique();
            $table->timestamps();
        });

        Schema::create('masyarakat', function (Blueprint $table) {
            $table->string('nik', 16)->primary();
            $table->string('nama_lengkap');
            $table->string('nomor_telepon', 20)->nullable();
            $table->text('alamat')->nullable();
            $table->enum('status_data', ['AKTIF', 'NONAKTIF'])->default('AKTIF')->index();
            $table->timestamps();
        });

        Schema::create('puskesmas', function (Blueprint $table) {
            $table->id('puskesmas_id');
            $table->unsignedBigInteger('kecamatan_id')->index();
            $table->string('nama_puskesmas')->unique();
            $table->text('alamat');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('nomor_telepon', 20)->nullable();
            $table->enum('status', ['AKTIF', 'NONAKTIF'])->default('AKTIF')->index();
            $table->timestamps();

            $table->foreign('kecamatan_id')->references('kecamatan_id')->on('kecamatan')->restrictOnDelete()->cascadeOnUpdate();
        });

        Schema::create('akun', function (Blueprint $table) {
            $table->id('akun_id');
            $table->string('nik', 16)->nullable()->unique();
            $table->unsignedBigInteger('puskesmas_id')->nullable()->index();
            $table->string('nama_lengkap');
            $table->string('email')->unique();
            $table->string('password_hash');
            $table->enum('role', ['MASYARAKAT', 'PETUGAS', 'ADMIN'])->index();
            $table->enum('status_akun', ['AKTIF', 'NONAKTIF'])->default('AKTIF')->index();
            $table->timestamps();

            $table->foreign('nik')->references('nik')->on('masyarakat')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('puskesmas_id')->references('puskesmas_id')->on('puskesmas')->restrictOnDelete()->cascadeOnUpdate();
        });

        Schema::create('layanan', function (Blueprint $table) {
            $table->id('layanan_id');
            $table->string('nama_layanan')->unique();
            $table->text('deskripsi')->nullable();
            $table->enum('status', ['AKTIF', 'NONAKTIF'])->default('AKTIF')->index();
            $table->timestamps();
        });

        Schema::create('puskesmas_layanan', function (Blueprint $table) {
            $table->id('puskesmas_layanan_id');
            $table->unsignedBigInteger('puskesmas_id');
            $table->unsignedBigInteger('layanan_id');
            $table->enum('status', ['AKTIF', 'NONAKTIF'])->default('AKTIF')->index();
            $table->timestamps();

            $table->foreign('puskesmas_id')->references('puskesmas_id')->on('puskesmas')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('layanan_id')->references('layanan_id')->on('layanan')->restrictOnDelete()->cascadeOnUpdate();
            $table->unique(['puskesmas_id', 'layanan_id']);
        });

        Schema::create('jadwal', function (Blueprint $table) {
            $table->id('jadwal_id');
            $table->unsignedBigInteger('puskesmas_layanan_id');
            $table->enum('hari', ['SENIN', 'SELASA', 'RABU', 'KAMIS', 'JUMAT', 'SABTU', 'MINGGU']);
            $table->time('jam_buka');
            $table->time('jam_tutup');
            $table->unsignedInteger('kapasitas')->default(50);
            $table->enum('status', ['AKTIF', 'NONAKTIF'])->default('AKTIF')->index();
            $table->timestamps();

            $table->foreign('puskesmas_layanan_id')->references('puskesmas_layanan_id')->on('puskesmas_layanan')->cascadeOnDelete()->cascadeOnUpdate();
            $table->unique(['puskesmas_layanan_id', 'hari']);
        });

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

        Schema::create('antrean', function (Blueprint $table) {
            $table->id('antrean_id');
            $table->string('nik', 16);
            $table->unsignedBigInteger('jadwal_id');
            $table->unsignedInteger('nomor_antrean');
            $table->date('tanggal_daftar');
            $table->enum('status_antrean', ['WAITING', 'CALLED', 'SERVING', 'COMPLETED', 'CANCELLED'])->default('WAITING')->index();
            $table->timestamps();

            $table->foreign('nik')->references('nik')->on('masyarakat')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('jadwal_id')->references('jadwal_id')->on('jadwal')->restrictOnDelete()->cascadeOnUpdate();
            $table->unique(['jadwal_id', 'tanggal_daftar', 'nomor_antrean']);
            $table->unique(['nik', 'jadwal_id', 'tanggal_daftar']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('antrean');
        Schema::dropIfExists('dokter');
        Schema::dropIfExists('jadwal');
        Schema::dropIfExists('puskesmas_layanan');
        Schema::dropIfExists('layanan');
        Schema::dropIfExists('akun');
        Schema::dropIfExists('puskesmas');
        Schema::dropIfExists('masyarakat');
        Schema::dropIfExists('kecamatan');
    }
};
