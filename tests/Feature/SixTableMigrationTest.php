<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SixTableMigrationTest extends TestCase
{
    public function test_existing_data_survives_the_six_table_migration(): void
    {
        $originalConnection = config('database.default');
        config(['database.connections.migration_audit' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        DB::setDefaultConnection('migration_audit');
        try {
            foreach (glob(database_path('migrations/2026_09_*.php')) as $file) {
                (require $file)->up();
            }
            DB::table('kecamatan')->insert(['kecamatan_id' => 1, 'nama_kecamatan' => 'Wilayah']);
            DB::table('puskesmas')->insert(['puskesmas_id' => 1, 'kecamatan_id' => 1, 'nama_puskesmas' => 'Faskes', 'alamat' => 'Surabaya']);
            DB::table('layanan')->insert([
                ['layanan_id' => 1, 'nama_layanan' => 'Umum'],
                ['layanan_id' => 2, 'nama_layanan' => 'Gigi'],
            ]);
            DB::table('masyarakat')->insert([
                ['nik' => '3578010101900001', 'nama_lengkap' => 'Aktif', 'alamat' => 'Alamat A', 'nomor_telepon' => '0812'],
                ['nik' => '3578010101900002', 'nama_lengkap' => 'Belum aktivasi', 'alamat' => 'Alamat B', 'nomor_telepon' => '0813'],
            ]);
            $hash = Hash::make('existing-password');
            DB::table('akun')->insert(['akun_id' => 12, 'nik' => '3578010101900001', 'nama_lengkap' => 'Aktif', 'email' => 'existing@example.test', 'password_hash' => $hash, 'role' => 'MASYARAKAT']);
            DB::table('puskesmas_layanan')->insert([
                ['puskesmas_layanan_id' => 1, 'puskesmas_id' => 1, 'layanan_id' => 1],
                ['puskesmas_layanan_id' => 2, 'puskesmas_id' => 1, 'layanan_id' => 2],
            ]);
            DB::table('dokter')->insert([
                ['puskesmas_layanan_id' => 1, 'nama_dokter' => 'dr. A', 'status' => 'AKTIF'],
                ['puskesmas_layanan_id' => 1, 'nama_dokter' => 'dr. B', 'status' => 'NONAKTIF'],
                ['puskesmas_layanan_id' => 2, 'nama_dokter' => 'dr. C', 'status' => 'AKTIF'],
            ]);
            DB::table('jadwal')->insert(['jadwal_id' => 9, 'puskesmas_layanan_id' => 1, 'hari' => 'SENIN', 'jam_buka' => '08:00', 'jam_tutup' => '12:00', 'kapasitas' => 25]);
            DB::table('antrean')->insert(['antrean_id' => 7, 'nik' => '3578010101900002', 'jadwal_id' => 9, 'nomor_antrean' => 3, 'tanggal_daftar' => '2026-10-05', 'status_antrean' => 'WAITING']);

            (require database_path('migrations/2026_10_02_000007_simplify_to_six_business_tables.php'))->up();

            $this->assertSame(2, DB::table('akun')->count());
            $this->assertSame($hash, DB::table('akun')->where('akun_id', 12)->value('password_hash'));
            $pending = DB::table('akun')->where('nik', '3578010101900002')->first();
            $this->assertNull($pending->password_hash);
            $this->assertSame('NONAKTIF', $pending->status_akun);
            $this->assertSame('Alamat B', $pending->alamat);
            $queue = DB::table('antrean')->where('antrean_id', 7)->first();
            $this->assertSame($pending->akun_id, $queue->akun_id);
            $this->assertSame(9, $queue->jadwal_id);
            $this->assertSame(3, $queue->nomor_antrean);
            $this->assertSame('dr. A; dr. B (nonaktif)', DB::table('jadwal')->where('jadwal_id', 9)->value('nama_dokter'));
            $this->assertSame('NONAKTIF', DB::table('jadwal')->where('layanan_id', 2)->value('status'));
            foreach (['masyarakat', 'dokter', 'puskesmas_layanan'] as $table) {
                $this->assertFalse(Schema::hasTable($table));
            }
            $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
        } finally {
            DB::purge('migration_audit');
            DB::setDefaultConnection($originalConnection);
        }
    }
}
