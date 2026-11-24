<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Puskesmas;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AnalyticsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_officer_can_open_their_dashboards_only(): void
    {
        $this->useWarehouse();
        $admin = $this->account(['role' => 'ADMIN']);
        $officer = $this->officer();
        $citizen = $this->account(['role' => 'MASYARAKAT', 'email' => 'warga-dw@example.test']);

        $this->actingAs($admin)->get(route('admin.analytics.index'))->assertOk()->assertSee('Dashboard analitik')->assertSee('aria-label="Drill-down kecamatan"', false);
        $this->actingAs($admin)->get(route('officer.analytics.index'))->assertForbidden();
        $this->actingAs($officer)->get(route('officer.analytics.index'))->assertOk()->assertSee('Puskesmas Uji Satu')->assertDontSee('aria-label="Drill-down kecamatan"', false);
        $this->actingAs($officer)->get(route('admin.analytics.data'))->assertForbidden();
        $this->actingAs($citizen)->get(route('admin.analytics.index'))->assertForbidden();
        $this->actingAs($citizen)->get(route('officer.analytics.data'))->assertForbidden();
    }

    public function test_admin_data_endpoint_returns_olap_views_and_does_not_sum_capacity_per_row(): void
    {
        $this->useWarehouse();
        $admin = $this->account(['role' => 'ADMIN']);

        $response = $this->actingAs($admin)->getJson(route('admin.analytics.data'))->assertOk();
        // 4 antrean, 1 batal; 3 slot jadwal × tanggal berkapasitas 10 → 3 terisi / 30 = 10%.
        $response->assertJsonPath('kpi.total', 4)
            ->assertJsonPath('kpi.pasien', 3)
            ->assertJsonPath('kpi.persen_selesai', 50)
            ->assertJsonPath('kpi.persen_batal', 25)
            ->assertJsonPath('kpi.persen_utilisasi', 10)
            ->assertJsonPath('region.level', 'kecamatan')
            ->assertJsonPath('utilization.kapasitas.0', 30)
            ->assertJsonPath('trend.granularity', 'hari')
            ->assertJsonCount(5, 'statuses')
            ->assertJsonCount(6, 'trend.data')
            ->assertJsonPath('daySession.0', ['category' => 'Senin', 'value' => 2, 'group' => 'Pagi']);

        // Drill-down ke satu kecamatan, lalu dice dengan bulan.
        $this->actingAs($admin)->getJson(route('admin.analytics.data', ['kecamatan' => 'Kecamatan A', 'tahun' => 2026, 'bulan' => 10]))
            ->assertOk()
            ->assertJsonPath('region.level', 'puskesmas')
            ->assertJsonPath('region.data.0.category', 'Puskesmas Uji Satu')
            ->assertJsonPath('kpi.total', 3);

        $this->actingAs($admin)->getJson(route('admin.analytics.data', ['bulan' => 11]))->assertOk()->assertJsonPath('kpi.total', 0);
        $this->actingAs($admin)->getJson(route('admin.analytics.data', ['bulan' => 13]))->assertUnprocessable();
    }

    public function test_officer_data_is_locked_to_assigned_puskesmas(): void
    {
        $this->useWarehouse();
        $officer = $this->officer();

        // Filter kecamatan lain diabaikan untuk petugas.
        $this->actingAs($officer)->getJson(route('officer.analytics.data', ['kecamatan' => 'Kecamatan B']))
            ->assertOk()
            ->assertJsonPath('kpi.total', 3)
            ->assertJsonPath('region', null)
            ->assertJsonPath('ranking.title', 'Utilisasi jadwal')
            ->assertJsonPath('ranking.highest.0.Layanan', 'Poli Umum');
    }

    public function test_dashboard_explains_how_to_run_etl_when_warehouse_is_unavailable(): void
    {
        config(['database.connections.dw' => ['driver' => 'sqlite', 'database' => storage_path('framework/testing/tidak-ada/dw.sqlite'), 'prefix' => '']]);
        DB::purge('dw');
        $admin = $this->account(['role' => 'ADMIN']);

        $this->actingAs($admin)->get(route('admin.analytics.index'))
            ->assertOk()
            ->assertSee('Data warehouse belum dapat dibaca')
            ->assertSee('php artisan dw:etl --full');
        $this->actingAs($admin)->getJson(route('admin.analytics.data'))->assertStatus(503);
    }

    private function useWarehouse(): void
    {
        config(['database.connections.dw' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
        DB::purge('dw');
        $schema = Schema::connection('dw');

        $schema->create('dim_waktu', function (Blueprint $table) {
            $table->integer('waktu_key')->primary();
            $table->date('tanggal');
            $table->string('nama_hari')->nullable();
            $table->integer('bulan');
            $table->integer('tahun');
        });
        $schema->create('dim_puskesmas', function (Blueprint $table) {
            $table->increments('puskesmas_key');
            $table->unsignedInteger('puskesmas_id');
            $table->string('nama_puskesmas');
            $table->string('kecamatan');
        });
        $schema->create('dim_layanan', function (Blueprint $table) {
            $table->increments('layanan_key');
            $table->unsignedInteger('layanan_id');
            $table->string('nama_layanan');
        });
        $schema->create('dim_jadwal', function (Blueprint $table) {
            $table->increments('jadwal_key');
            $table->unsignedInteger('jadwal_id');
            $table->string('hari');
            $table->string('sesi');
            $table->text('nama_dokter')->nullable();
        });
        $schema->create('fact_antrean', function (Blueprint $table) {
            $table->increments('antrean_key');
            foreach (['waktu_key', 'puskesmas_key', 'layanan_key', 'jadwal_key', 'antrean_id', 'akun_id', 'jumlah_antrean', 'is_completed', 'is_cancelled', 'kapasitas_jadwal'] as $column) {
                $table->integer($column);
            }
            $table->string('status_antrean');
        });

        $dw = DB::connection('dw');
        $dw->table('dim_waktu')->insert([
            ['waktu_key' => 20261005, 'tanggal' => '2026-10-05', 'nama_hari' => 'Senin', 'bulan' => 10, 'tahun' => 2026],
            ['waktu_key' => 20261006, 'tanggal' => '2026-10-06', 'nama_hari' => 'Selasa', 'bulan' => 10, 'tahun' => 2026],
        ]);
        $dw->table('dim_puskesmas')->insert([
            ['puskesmas_id' => 1, 'nama_puskesmas' => 'Puskesmas Uji Satu', 'kecamatan' => 'Kecamatan A'],
            ['puskesmas_id' => 2, 'nama_puskesmas' => 'Puskesmas Uji Dua', 'kecamatan' => 'Kecamatan B'],
        ]);
        $dw->table('dim_layanan')->insert([['layanan_id' => 1, 'nama_layanan' => 'Poli Umum']]);
        $dw->table('dim_jadwal')->insert([
            ['jadwal_id' => 1, 'hari' => 'SENIN', 'sesi' => 'PAGI', 'nama_dokter' => 'dr. Uji'],
            ['jadwal_id' => 2, 'hari' => 'SENIN', 'sesi' => 'SIANG', 'nama_dokter' => null],
        ]);

        $fact = fn ($id, $waktu, $puskesmas, $jadwal, $akun, $status) => [
            'waktu_key' => $waktu, 'puskesmas_key' => $puskesmas, 'layanan_key' => 1, 'jadwal_key' => $jadwal,
            'antrean_id' => $id, 'akun_id' => $akun, 'status_antrean' => $status, 'jumlah_antrean' => 1,
            'is_completed' => (int) ($status === 'COMPLETED'), 'is_cancelled' => (int) ($status === 'CANCELLED'), 'kapasitas_jadwal' => 10,
        ];
        $dw->table('fact_antrean')->insert([
            $fact(1, 20261005, 1, 1, 101, 'COMPLETED'),
            $fact(2, 20261005, 1, 1, 102, 'CANCELLED'),
            $fact(3, 20261006, 1, 1, 101, 'COMPLETED'),
            $fact(4, 20261005, 2, 2, 103, 'WAITING'),
        ]);

        // Tabel agregat dibangun dengan query yang sama seperti ETL.
        $schema->create('agg_antrean_harian', function (Blueprint $table) {
            foreach (['waktu_key', 'puskesmas_key', 'layanan_key', 'jadwal_key', 'jumlah_antrean', 'jumlah_selesai', 'jumlah_batal', 'kapasitas_jadwal'] as $column) {
                $table->integer($column);
            }
            $table->string('status_antrean');
        });
        $dw->statement(
            'INSERT INTO agg_antrean_harian
               (waktu_key, puskesmas_key, layanan_key, jadwal_key, status_antrean,
                jumlah_antrean, jumlah_selesai, jumlah_batal, kapasitas_jadwal)
             SELECT waktu_key, MAX(puskesmas_key), MAX(layanan_key), jadwal_key, status_antrean,
                    SUM(jumlah_antrean), SUM(is_completed), SUM(is_cancelled), MAX(kapasitas_jadwal)
             FROM fact_antrean
             GROUP BY waktu_key, jadwal_key, status_antrean'
        );
    }

    public function test_results_are_cached_until_etl_flushes_the_cache(): void
    {
        $this->useWarehouse();
        $admin = $this->account(['role' => 'ADMIN']);
        $this->actingAs($admin)->getJson(route('admin.analytics.data'))->assertJsonPath('kpi.total', 4);

        // Perubahan langsung di data warehouse belum terlihat karena hasil masih dari cache.
        DB::connection('dw')->table('agg_antrean_harian')->where('status_antrean', 'WAITING')->update(['jumlah_antrean' => 5]);
        $this->actingAs($admin)->getJson(route('admin.analytics.data'))->assertJsonPath('kpi.total', 4);

        // Setelah ETL mengosongkan cache, angka baru terbaca.
        \App\Support\WarehouseAnalytics::flushCache();
        $this->actingAs($admin)->getJson(route('admin.analytics.data'))->assertJsonPath('kpi.total', 8);
    }

    private function account(array $attributes): User
    {
        return User::create(User::factory()->raw($attributes));
    }

    /** Petugas pada puskesmas aplikasi yang puskesmas_id-nya cocok dengan dim_puskesmas.puskesmas_id = 1. */
    private function officer(): User
    {
        $district = District::create(['nama_kecamatan' => 'Kecamatan A']);
        $puskesmas = Puskesmas::create(['kecamatan_id' => $district->kecamatan_id, 'nama_puskesmas' => 'Puskesmas Uji Satu', 'alamat' => 'Jl. Uji', 'status' => 'AKTIF']);
        $this->assertSame(1, (int) $puskesmas->puskesmas_id);

        return $this->account(['role' => 'PETUGAS', 'email' => 'petugas-dw@example.test', 'puskesmas_id' => $puskesmas->puskesmas_id]);
    }
}
