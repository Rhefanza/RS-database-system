<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('akun', function (Blueprint $table) {
            $table->dropForeign(['nik']);
            $table->string('email')->nullable()->change();
            $table->string('password_hash')->nullable()->change();
            $table->string('nomor_telepon', 20)->nullable();
            $table->text('alamat')->nullable();
            $table->enum('status_data', ['AKTIF', 'NONAKTIF'])->default('AKTIF');
        });

        foreach (DB::table('masyarakat')->orderBy('nik')->get() as $citizen) {
            $account = DB::table('akun')->where('nik', $citizen->nik)->first();
            $data = [
                'nomor_telepon' => $citizen->nomor_telepon,
                'alamat' => $citizen->alamat,
                'status_data' => $citizen->status_data,
            ];
            if ($account) {
                DB::table('akun')->where('akun_id', $account->akun_id)->update($data);
            } else {
                DB::table('akun')->insert($data + [
                    'nik' => $citizen->nik, 'nama_lengkap' => $citizen->nama_lengkap,
                    'role' => 'MASYARAKAT', 'status_akun' => 'NONAKTIF',
                    'email' => null, 'password_hash' => null,
                    'created_at' => $citizen->created_at, 'updated_at' => $citizen->updated_at,
                ]);
            }
        }

        Schema::table('jadwal', function (Blueprint $table) {
            $table->unsignedBigInteger('puskesmas_id')->nullable();
            $table->unsignedBigInteger('layanan_id')->nullable();
            $table->text('nama_dokter')->nullable();
            $table->text('spesialisasi')->nullable();
        });
        foreach (DB::table('puskesmas_layanan')->get() as $relation) {
            $doctors = DB::table('dokter')->where('puskesmas_layanan_id', $relation->puskesmas_layanan_id)->orderBy('dokter_id')->get();
            // Retain all old names, including inactive doctors, as readable notes.
            $data = [
                'puskesmas_id' => $relation->puskesmas_id,
                'layanan_id' => $relation->layanan_id,
                'nama_dokter' => $doctors->map(fn ($doctor) => $doctor->nama_dokter.($doctor->status === 'NONAKTIF' ? ' (nonaktif)' : ''))->implode('; ') ?: null,
                'spesialisasi' => $doctors->pluck('spesialisasi')->filter()->unique()->implode('; ') ?: null,
            ];
            $schedules = DB::table('jadwal')->where('puskesmas_layanan_id', $relation->puskesmas_layanan_id);
            if (! (clone $schedules)->exists()) {
                // Preserve an unscheduled old service as an inactive draft for the officer.
                DB::table('jadwal')->insert($data + [
                    'puskesmas_layanan_id' => $relation->puskesmas_layanan_id,
                    'hari' => 'SENIN', 'jam_buka' => '08:00', 'jam_tutup' => '12:00',
                    'kapasitas' => 50, 'status' => 'NONAKTIF',
                    'created_at' => $relation->created_at, 'updated_at' => $relation->updated_at,
                ]);
            } else {
                if ($relation->status === 'NONAKTIF') {
                    $data['status'] = 'NONAKTIF';
                }
                $schedules->update($data);
            }
        }
        Schema::table('jadwal', function (Blueprint $table) {
            $table->unsignedBigInteger('puskesmas_id')->nullable(false)->change();
            $table->unsignedBigInteger('layanan_id')->nullable(false)->change();
            $table->foreign('puskesmas_id')->references('puskesmas_id')->on('puskesmas')->restrictOnDelete();
            $table->foreign('layanan_id')->references('layanan_id')->on('layanan')->restrictOnDelete();
            $table->dropForeign(['puskesmas_layanan_id']);
            $table->dropUnique(['puskesmas_layanan_id', 'hari']);
            $table->dropColumn('puskesmas_layanan_id');
            $table->unique(['puskesmas_id', 'layanan_id', 'hari']);
        });
        Schema::table('antrean', function (Blueprint $table) {
            $table->unsignedBigInteger('akun_id')->nullable();
        });
        foreach (DB::table('akun')->whereNotNull('nik')->get(['akun_id', 'nik']) as $account) {
            DB::table('antrean')->where('nik', $account->nik)->update(['akun_id' => $account->akun_id]);
        }
        if (DB::table('antrean')->whereNull('akun_id')->exists()) {
            throw new RuntimeException('Ada antrean tanpa akun. Pulihkan cadangan sebelum mencoba kembali.');
        }
        Schema::table('antrean', function (Blueprint $table) {
            $table->unsignedBigInteger('akun_id')->nullable(false)->change();
            $table->foreign('akun_id')->references('akun_id')->on('akun')->restrictOnDelete();
            $table->dropForeign(['nik']);
            $table->dropIndex('antrean_nik_harden_index');
            $table->dropIndex('antrean_nik_jadwal_tanggal_index');
            $table->dropColumn('nik');
            $table->index(['akun_id', 'jadwal_id', 'tanggal_daftar']);
        });
        Schema::drop('dokter');
        Schema::drop('puskesmas_layanan');
        Schema::drop('masyarakat');
    }

    public function down(): void
    {
        throw new RuntimeException('Migrasi penggabungan data tidak dapat dibalik otomatis. Gunakan cadangan database sebelum migrasi.');
    }
};
