<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('antrean', function (Blueprint $table) {
            // MySQL dapat memakai indeks unik lama sebagai penopang foreign key NIK.
            // Sediakan indeks pengganti sebelum indeks unik tersebut dilepas.
            $table->index('nik', 'antrean_nik_harden_index');
            $table->dropUnique(['nik', 'jadwal_id', 'tanggal_daftar']);
            $table->index(['nik', 'jadwal_id', 'tanggal_daftar'], 'antrean_nik_jadwal_tanggal_index');
        });

        $usedEmails = DB::table('akun')->pluck('email', 'akun_id')->all();
        $officers = DB::table('akun')
            ->leftJoin('puskesmas', 'akun.puskesmas_id', '=', 'puskesmas.puskesmas_id')
            ->where('akun.role', 'PETUGAS')
            ->orderBy('akun.akun_id')
            ->get(['akun.akun_id', 'akun.puskesmas_id', 'puskesmas.nama_puskesmas']);

        foreach ($officers as $officer) {
            if (! $officer->puskesmas_id || ! $officer->nama_puskesmas) {
                DB::table('akun')->where('akun_id', $officer->akun_id)->update(['status_akun' => 'NONAKTIF']);

                continue;
            }

            $area = preg_replace('/^Puskesmas\s+/i', '', $officer->nama_puskesmas);
            $slug = Str::slug($area ?: $officer->nama_puskesmas, '_');
            $base = 'petugas_'.($slug ?: $officer->puskesmas_id);
            $email = $base.'@test';
            $number = 2;

            while (in_array($email, array_filter($usedEmails, fn ($value, $key) => (int) $key !== (int) $officer->akun_id, ARRAY_FILTER_USE_BOTH), true)) {
                $email = $base.'_'.$number.'@test';
                $number++;
            }

            DB::table('akun')->where('akun_id', $officer->akun_id)->update(['email' => $email]);
            $usedEmails[$officer->akun_id] = $email;
        }
    }

    public function down(): void
    {
        Schema::table('antrean', function (Blueprint $table) {
            $table->dropIndex('antrean_nik_jadwal_tanggal_index');
            $table->unique(['nik', 'jadwal_id', 'tanggal_daftar']);
            $table->dropIndex('antrean_nik_harden_index');
        });
    }
};
