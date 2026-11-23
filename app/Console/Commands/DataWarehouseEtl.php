<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * ETL dari database aplikasi (enam tabel bisnis) ke data warehouse star schema.
 *
 * Extract  : membaca kecamatan, puskesmas, layanan, jadwal, dan antrean dari database aplikasi.
 * Transform: menggabungkan kecamatan ke puskesmas, memecah tanggal, menentukan sesi, mengubah status menjadi flag.
 * Load     : upsert ke dim_waktu, dim_puskesmas, dim_layanan, dim_jadwal, dan fact_antrean.
 *
 * Database aplikasi dan data warehouse harus berada pada server MySQL yang sama.
 */
class DataWarehouseEtl extends Command
{
    protected $signature = 'dw:etl {--full : Muat ulang seluruh antrean, bukan hanya yang baru atau berubah}';

    protected $description = 'Jalankan ETL dari database aplikasi ke data warehouse (puskesmasku_dw)';

    /** Antrean yang berubah dalam rentang ini dimuat ulang pada mode inkremental. */
    private const INCREMENTAL_DAYS = 2;

    public function handle(): int
    {
        $source = config('database.connections.'.config('database.default').'.database');
        $target = config('database.connections.dw.database');

        if (! in_array(config('database.connections.'.config('database.default').'.driver'), ['mysql', 'mariadb'], true)) {
            $this->error('ETL data warehouse hanya mendukung database aplikasi MySQL/MariaDB.');

            return self::FAILURE;
        }

        try {
            DB::connection()->statement('CREATE DATABASE IF NOT EXISTS '.$this->quote($target));
            $dw = DB::connection('dw');
            $this->createSchema($dw);
            $started = microtime(true);
            $counts = $this->load($dw, $this->quote($source));
        } catch (Throwable $exception) {
            $this->error('ETL gagal: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Tabel', 'Jumlah baris'], collect($counts)->map(fn ($count, $table) => [$table, $count])->values());
        $this->info(sprintf('ETL %s selesai dalam %.2f detik.', $this->option('full') ? 'penuh' : 'inkremental', microtime(true) - $started));

        return self::SUCCESS;
    }

    private function createSchema(Connection $dw): void
    {
        $dw->statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS dim_waktu (
              waktu_key      INT PRIMARY KEY,
              tanggal        DATE NOT NULL,
              nama_hari      VARCHAR(10),
              minggu_ke      TINYINT,
              bulan          TINYINT,
              nama_bulan     VARCHAR(15),
              kuartal        TINYINT,
              tahun          SMALLINT,
              is_akhir_pekan BOOLEAN
            )
        SQL);
        $dw->statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS dim_puskesmas (
              puskesmas_key  INT AUTO_INCREMENT PRIMARY KEY,
              puskesmas_id   INT UNSIGNED NOT NULL,
              nama_puskesmas VARCHAR(255),
              kecamatan      VARCHAR(255),
              alamat         TEXT,
              latitude       DECIMAL(10,7),
              longitude      DECIMAL(10,7),
              status         VARCHAR(20),
              UNIQUE KEY uq_puskesmas_id (puskesmas_id)
            )
        SQL);
        $dw->statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS dim_layanan (
              layanan_key    INT AUTO_INCREMENT PRIMARY KEY,
              layanan_id     INT UNSIGNED NOT NULL,
              nama_layanan   VARCHAR(255),
              status         VARCHAR(20),
              UNIQUE KEY uq_layanan_id (layanan_id)
            )
        SQL);
        $dw->statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS dim_jadwal (
              jadwal_key     INT AUTO_INCREMENT PRIMARY KEY,
              jadwal_id      INT UNSIGNED NOT NULL,
              hari           VARCHAR(10),
              jam_buka       TIME,
              jam_tutup      TIME,
              sesi           VARCHAR(10),
              nama_dokter    TEXT,
              spesialisasi   TEXT,
              kapasitas      INT,
              UNIQUE KEY uq_jadwal_id (jadwal_id)
            )
        SQL);
        $dw->statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS fact_antrean (
              antrean_key      INT AUTO_INCREMENT PRIMARY KEY,
              waktu_key        INT NOT NULL,
              puskesmas_key    INT NOT NULL,
              layanan_key      INT NOT NULL,
              jadwal_key       INT NOT NULL,
              antrean_id       INT UNSIGNED NOT NULL,
              akun_id          INT UNSIGNED NOT NULL,
              nomor_antrean    INT,
              status_antrean   VARCHAR(10),
              jumlah_antrean   TINYINT DEFAULT 1,
              is_completed     TINYINT,
              is_cancelled     TINYINT,
              kapasitas_jadwal INT,
              UNIQUE (antrean_id),
              FOREIGN KEY (waktu_key)     REFERENCES dim_waktu(waktu_key),
              FOREIGN KEY (puskesmas_key) REFERENCES dim_puskesmas(puskesmas_key),
              FOREIGN KEY (layanan_key)   REFERENCES dim_layanan(layanan_key),
              FOREIGN KEY (jadwal_key)    REFERENCES dim_jadwal(jadwal_key)
            )
        SQL);

        // Tabel yang dibuat manual dari DDL laporan mungkin belum memiliki kunci unik ID asal.
        foreach (['dim_puskesmas' => 'puskesmas_id', 'dim_layanan' => 'layanan_id', 'dim_jadwal' => 'jadwal_id'] as $table => $column) {
            $hasUnique = $dw->selectOne(
                'SELECT COUNT(*) AS jumlah FROM information_schema.statistics
                 WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? AND non_unique = 0',
                [$table, $column]
            )->jumlah > 0;
            if (! $hasUnique) {
                $dw->statement("ALTER TABLE {$table} ADD UNIQUE uq_{$column} ({$column})");
            }
        }
    }

    /** @return array<string, int> */
    private function load(Connection $dw, string $source): array
    {
        $dw->statement("SET lc_time_names = 'id_ID'");

        $dw->transaction(function () use ($dw, $source) {
            // Dimensi berukuran kecil sehingga selalu dimuat penuh.
            $dw->statement(<<<SQL
                INSERT INTO dim_puskesmas (puskesmas_id, nama_puskesmas, kecamatan, alamat, latitude, longitude, status)
                SELECT * FROM (
                  SELECT p.puskesmas_id, p.nama_puskesmas, k.nama_kecamatan AS kecamatan,
                         p.alamat, p.latitude, p.longitude, p.status
                  FROM {$source}.puskesmas p
                  LEFT JOIN {$source}.kecamatan k ON k.kecamatan_id = p.kecamatan_id
                ) AS src
                ON DUPLICATE KEY UPDATE
                  nama_puskesmas = src.nama_puskesmas, kecamatan = src.kecamatan, alamat = src.alamat,
                  latitude = src.latitude, longitude = src.longitude, status = src.status
            SQL);

            $dw->statement(<<<SQL
                INSERT INTO dim_layanan (layanan_id, nama_layanan, status)
                SELECT * FROM (
                  SELECT l.layanan_id, l.nama_layanan, l.status FROM {$source}.layanan l
                ) AS src
                ON DUPLICATE KEY UPDATE nama_layanan = src.nama_layanan, status = src.status
            SQL);

            $dw->statement(<<<SQL
                INSERT INTO dim_jadwal (jadwal_id, hari, jam_buka, jam_tutup, sesi, nama_dokter, spesialisasi, kapasitas)
                SELECT * FROM (
                  SELECT j.jadwal_id, j.hari, j.jam_buka, j.jam_tutup,
                         CASE WHEN j.jam_buka < '12:00:00' THEN 'PAGI' ELSE 'SIANG' END AS sesi,
                         j.nama_dokter, j.spesialisasi, j.kapasitas
                  FROM {$source}.jadwal j
                ) AS src
                ON DUPLICATE KEY UPDATE
                  hari = src.hari, jam_buka = src.jam_buka, jam_tutup = src.jam_tutup, sesi = src.sesi,
                  nama_dokter = src.nama_dokter, spesialisasi = src.spesialisasi, kapasitas = src.kapasitas
            SQL);

            // Fakta: mode inkremental memuat antrean baru (ID lebih besar dari yang sudah ada)
            // dan antrean yang berubah dalam beberapa hari terakhir.
            $filter = '';
            $bindings = [];
            if (! $this->option('full')) {
                $filter = 'WHERE a.antrean_id > ? OR a.updated_at >= ?';
                $bindings = [
                    (int) $dw->table('fact_antrean')->max('antrean_id'),
                    now()->subDays(self::INCREMENTAL_DAYS)->toDateTimeString(),
                ];
            }

            $dw->statement(<<<SQL
                INSERT INTO dim_waktu (waktu_key, tanggal, nama_hari, minggu_ke, bulan, nama_bulan, kuartal, tahun, is_akhir_pekan)
                SELECT * FROM (
                  SELECT DISTINCT
                    DATE_FORMAT(a.tanggal_daftar, '%Y%m%d') + 0 AS waktu_key,
                    a.tanggal_daftar AS tanggal,
                    DAYNAME(a.tanggal_daftar) AS nama_hari,
                    WEEK(a.tanggal_daftar, 1) AS minggu_ke,
                    MONTH(a.tanggal_daftar) AS bulan,
                    MONTHNAME(a.tanggal_daftar) AS nama_bulan,
                    QUARTER(a.tanggal_daftar) AS kuartal,
                    YEAR(a.tanggal_daftar) AS tahun,
                    DAYOFWEEK(a.tanggal_daftar) IN (1, 7) AS is_akhir_pekan
                  FROM {$source}.antrean a
                  {$filter}
                ) AS src
                ON DUPLICATE KEY UPDATE tanggal = src.tanggal
            SQL, $bindings);

            $dw->statement(<<<SQL
                INSERT INTO fact_antrean
                  (waktu_key, puskesmas_key, layanan_key, jadwal_key, antrean_id, akun_id,
                   nomor_antrean, status_antrean, jumlah_antrean, is_completed, is_cancelled, kapasitas_jadwal)
                SELECT * FROM (
                  SELECT
                    DATE_FORMAT(a.tanggal_daftar, '%Y%m%d') + 0 AS waktu_key,
                    dp.puskesmas_key, dl.layanan_key, dj.jadwal_key,
                    a.antrean_id, a.akun_id, a.nomor_antrean, a.status_antrean,
                    1 AS jumlah_antrean,
                    a.status_antrean = 'COMPLETED' AS is_completed,
                    a.status_antrean = 'CANCELLED' AS is_cancelled,
                    j.kapasitas AS kapasitas_jadwal
                  FROM {$source}.antrean a
                  JOIN {$source}.jadwal j ON j.jadwal_id = a.jadwal_id
                  JOIN dim_puskesmas dp ON dp.puskesmas_id = j.puskesmas_id
                  JOIN dim_layanan dl   ON dl.layanan_id   = j.layanan_id
                  JOIN dim_jadwal dj    ON dj.jadwal_id    = j.jadwal_id
                  {$filter}
                ) AS src
                ON DUPLICATE KEY UPDATE
                  status_antrean = src.status_antrean, is_completed = src.is_completed,
                  is_cancelled = src.is_cancelled, kapasitas_jadwal = src.kapasitas_jadwal
            SQL, $bindings);
        });

        return collect(['dim_waktu', 'dim_puskesmas', 'dim_layanan', 'dim_jadwal', 'fact_antrean'])
            ->mapWithKeys(fn ($table) => [$table => $dw->table($table)->count()])
            ->all();
    }

    private function quote(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }
}
