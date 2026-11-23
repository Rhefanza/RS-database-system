<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/**
 * Query OLAP ke data warehouse (koneksi "dw") untuk dashboard analitik.
 *
 * Hasilnya sudah berbentuk data chart GPT-Vis (category/value/group, time/value/group, dll.)
 * sehingga Blade cukup meneruskannya ke gptVis.render().
 *
 * Catatan ukuran:
 * - kapasitas_jadwal berulang di setiap baris antrean pada jadwal & tanggal yang sama, sehingga
 *   kapasitas selalu diambil MAX per (jadwal, tanggal) lalu dijumlahkan, tidak pernah di-SUM per baris.
 * - Utilisasi = antrean yang tidak dibatalkan / kapasitas slot (jadwal × tanggal) yang memiliki antrean.
 * - DW hanya menyimpan status akhir antrean, sehingga tidak ada metrik waktu tunggu.
 */
class WarehouseAnalytics
{
    public const STATUS_GROUPS = ['Selesai', 'Dibatalkan', 'Aktif'];

    private const STATUSES = ['WAITING', 'CALLED', 'SERVING', 'COMPLETED', 'CANCELLED'];

    private const DAYS = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];

    private const MONTHS = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    /** Tren harian sampai 31 tanggal, mingguan sampai ±6 bulan, selebihnya bulanan. */
    private const MAX_DAILY_DATES = 31;

    private const MAX_WEEKLY_DATES = 180;

    private const STATUS_GROUP_SQL = "CASE f.status_antrean WHEN 'COMPLETED' THEN 'Selesai' WHEN 'CANCELLED' THEN 'Dibatalkan' ELSE 'Aktif' END";

    private Connection $dw;

    /**
     * @param  array{tahun?: int|string|null, bulan?: int|string|null, kecamatan?: string|null, layanan?: int|string|null}  $filters
     * @param  int|null  $puskesmasId  ID puskesmas asal (dari tabel aplikasi) untuk dashboard petugas.
     */
    public function __construct(private array $filters = [], private ?int $puskesmasId = null)
    {
        $this->dw = DB::connection('dw');
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        [$where, $bindings] = $this->where();
        $from = "FROM fact_antrean f
                 JOIN dim_waktu w     ON w.waktu_key     = f.waktu_key
                 JOIN dim_puskesmas p ON p.puskesmas_key = f.puskesmas_key
                 JOIN dim_layanan l   ON l.layanan_key   = f.layanan_key
                 JOIN dim_jadwal j    ON j.jadwal_key    = f.jadwal_key
                 {$where}";

        $kpi = $this->kpi($from, $bindings);

        return [
            'kpi' => $kpi,
            'trend' => $this->trend($from, $bindings),
            'region' => $this->puskesmasId ? null : $this->region($from, $bindings),
            'services' => $this->services($from, $bindings),
            'utilization' => $this->utilization($from, $bindings),
            'daySession' => $this->daySession($from, $bindings),
            'statuses' => $this->statuses($from, $bindings),
            'ranking' => $this->puskesmasId ? $this->scheduleRanking($from, $bindings) : $this->puskesmasRanking($from, $bindings),
        ];
    }

    /** @return array<string, mixed> */
    public function meta(): array
    {
        $scope = $this->puskesmasId ? 'WHERE p.puskesmas_id = ?' : '';
        $bindings = $this->puskesmasId ? [$this->puskesmasId] : [];
        $coverage = $this->dw->selectOne(
            "SELECT MIN(w.tanggal) AS awal, MAX(w.tanggal) AS akhir, COUNT(*) AS total
             FROM fact_antrean f
             JOIN dim_waktu w ON w.waktu_key = f.waktu_key
             JOIN dim_puskesmas p ON p.puskesmas_key = f.puskesmas_key {$scope}",
            $bindings
        );

        return [
            'coverage' => ['awal' => $coverage->awal, 'akhir' => $coverage->akhir, 'total' => (int) $coverage->total],
            'puskesmas' => $this->puskesmasId
                ? $this->dw->table('dim_puskesmas')->where('puskesmas_id', $this->puskesmasId)->value('nama_puskesmas')
                : null,
            'options' => [
                'tahun' => $this->dw->table('dim_waktu')->distinct()->orderByDesc('tahun')->pluck('tahun')->map(fn ($year) => (int) $year)->values(),
                'bulan' => collect(self::MONTHS)->map(fn ($name, $number) => ['value' => $number, 'label' => $name])->values(),
                'kecamatan' => $this->puskesmasId ? [] : $this->dw->table('dim_puskesmas')->whereNotNull('kecamatan')->distinct()->orderBy('kecamatan')->pluck('kecamatan'),
                'layanan' => $this->dw->table('dim_layanan')->orderBy('nama_layanan')->get(['layanan_key', 'nama_layanan'])
                    ->map(fn ($row) => ['value' => (int) $row->layanan_key, 'label' => $row->nama_layanan]),
            ],
        ];
    }

    /** SLICE & DICE: setiap filter mengunci satu dimensi; petugas selalu dikunci ke puskesmasnya. */
    private function where(): array
    {
        $conditions = [];
        $bindings = [];
        if ($this->puskesmasId) {
            $conditions[] = 'p.puskesmas_id = ?';
            $bindings[] = $this->puskesmasId;
        }
        if (! empty($this->filters['tahun'])) {
            $conditions[] = 'w.tahun = ?';
            $bindings[] = (int) $this->filters['tahun'];
        }
        if (! empty($this->filters['bulan'])) {
            $conditions[] = 'w.bulan = ?';
            $bindings[] = (int) $this->filters['bulan'];
        }
        if (! $this->puskesmasId && ! empty($this->filters['kecamatan'])) {
            $conditions[] = 'p.kecamatan = ?';
            $bindings[] = $this->filters['kecamatan'];
        }
        if (! empty($this->filters['layanan'])) {
            $conditions[] = 'f.layanan_key = ?';
            $bindings[] = (int) $this->filters['layanan'];
        }

        return [$conditions ? 'WHERE '.implode(' AND ', $conditions) : '', $bindings];
    }

    /** Satu baris per slot (jadwal × tanggal): kapasitas diambil sekali, bukan dijumlah per antrean. */
    private function slots(string $from, string $extraColumns = '', string $extraGroup = ''): string
    {
        return "SELECT f.jadwal_key, f.waktu_key{$extraColumns},
                       SUM(f.jumlah_antrean) AS jumlah,
                       SUM(f.jumlah_antrean) - SUM(f.is_cancelled) AS terisi,
                       SUM(f.is_completed) AS selesai,
                       SUM(f.is_cancelled) AS batal,
                       MAX(f.kapasitas_jadwal) AS kapasitas
                {$from}
                GROUP BY f.jadwal_key, f.waktu_key{$extraGroup}";
    }

    private function kpi(string $from, array $bindings): array
    {
        $row = $this->dw->selectOne(
            'SELECT COALESCE(SUM(x.jumlah), 0) AS total, COALESCE(SUM(x.terisi), 0) AS terisi,
                    COALESCE(SUM(x.selesai), 0) AS selesai, COALESCE(SUM(x.batal), 0) AS batal,
                    COALESCE(SUM(x.kapasitas), 0) AS kapasitas
             FROM ('.$this->slots($from).') x',
            $bindings
        );
        $pasien = $this->dw->selectOne("SELECT COUNT(DISTINCT f.akun_id) AS jumlah {$from}", $bindings);

        return [
            'total' => (int) $row->total,
            'pasien' => (int) $pasien->jumlah,
            'persen_selesai' => $this->percent($row->selesai, $row->total),
            'persen_batal' => $this->percent($row->batal, $row->total),
            'persen_utilisasi' => $this->percent($row->terisi, $row->kapasitas),
        ];
    }

    /** ROLL-UP: tanggal → minggu → bulan sesuai panjang rentang, dipecah per kelompok status (line, group = status). */
    private function trend(string $from, array $bindings): array
    {
        $group = self::STATUS_GROUP_SQL;
        $rows = $this->dw->select(
            "SELECT w.tanggal, {$group} AS kelompok, SUM(f.jumlah_antrean) AS total
             {$from} GROUP BY w.tanggal, {$group} ORDER BY w.tanggal",
            $bindings
        );

        $dates = count(array_unique(array_map(fn ($row) => $row->tanggal, $rows)));
        $granularity = $dates > self::MAX_WEEKLY_DATES ? 'bulan' : ($dates > self::MAX_DAILY_DATES ? 'minggu' : 'hari');

        // Agregasi periode dilakukan di PHP agar query tetap portabel; titik kosong diisi 0.
        $values = [];
        foreach ($rows as $row) {
            $date = Carbon::parse($row->tanggal);
            $label = match ($granularity) {
                'bulan' => self::MONTHS[$date->month].' '.$date->year,
                'minggu' => 'Mg '.$date->copy()->startOfWeek()->format('d/m/y'),
                default => $date->format('d/m'),
            };
            $values[$label][$row->kelompok] = ($values[$label][$row->kelompok] ?? 0) + (int) $row->total;
        }

        $data = [];
        foreach ($values as $label => $statuses) {
            foreach (self::STATUS_GROUPS as $status) {
                $data[] = ['time' => $label, 'value' => $statuses[$status] ?? 0, 'group' => $status];
            }
        }

        return ['granularity' => $granularity, 'data' => $data];
    }

    /** DRILL-DOWN: kecamatan → puskesmas di kecamatan terpilih. */
    private function region(string $from, array $bindings): array
    {
        $level = empty($this->filters['kecamatan']) ? 'p.kecamatan' : 'p.nama_puskesmas';
        $rows = $this->dw->select("SELECT {$level} AS nama, SUM(f.jumlah_antrean) AS total {$from} GROUP BY {$level} ORDER BY total DESC", $bindings);

        return [
            'level' => empty($this->filters['kecamatan']) ? 'kecamatan' : 'puskesmas',
            'kecamatan' => $this->filters['kecamatan'] ?? null,
            'data' => array_map(fn ($row) => ['category' => $row->nama, 'value' => (int) $row->total], $rows),
        ];
    }

    /** Layanan × kelompok status (column bertumpuk). */
    private function services(string $from, array $bindings): array
    {
        $group = self::STATUS_GROUP_SQL;
        $rows = $this->dw->select(
            "SELECT l.nama_layanan, {$group} AS kelompok, SUM(f.jumlah_antrean) AS total
             {$from} GROUP BY l.nama_layanan, {$group}",
            $bindings
        );

        $totals = [];
        $values = [];
        foreach ($rows as $row) {
            $totals[$row->nama_layanan] = ($totals[$row->nama_layanan] ?? 0) + (int) $row->total;
            $values[$row->nama_layanan][$row->kelompok] = (int) $row->total;
        }
        arsort($totals);

        $data = [];
        foreach (array_keys($totals) as $service) {
            foreach (self::STATUS_GROUPS as $status) {
                $data[] = ['category' => $service, 'value' => $values[$service][$status] ?? 0, 'group' => $status];
            }
        }

        return $data;
    }

    /** Kapasitas vs utilisasi per layanan (dual-axes). */
    private function utilization(string $from, array $bindings): array
    {
        $rows = $this->dw->select(
            'SELECT x.nama_layanan, SUM(x.terisi) AS terisi, SUM(x.kapasitas) AS kapasitas
             FROM ('.$this->slots($from, ', l.nama_layanan', ', l.nama_layanan').') x
             GROUP BY x.nama_layanan ORDER BY kapasitas DESC',
            $bindings
        );

        return [
            'categories' => array_map(fn ($row) => $row->nama_layanan, $rows),
            'kapasitas' => array_map(fn ($row) => (int) $row->kapasitas, $rows),
            'terisi' => array_map(fn ($row) => (int) $row->terisi, $rows),
            'utilisasi' => array_map(fn ($row) => $this->percent($row->terisi, $row->kapasitas), $rows),
        ];
    }

    /** Hari × sesi (column grouped). Hari dihitung di PHP agar tidak bergantung pada bahasa nama_hari. */
    private function daySession(string $from, array $bindings): array
    {
        $rows = $this->dw->select("SELECT w.tanggal, j.sesi, SUM(f.jumlah_antrean) AS total {$from} GROUP BY w.tanggal, j.sesi", $bindings);

        $values = [];
        foreach ($rows as $row) {
            $day = Carbon::parse($row->tanggal)->dayOfWeekIso;
            $session = $row->sesi === 'PAGI' ? 'Pagi' : 'Siang';
            $values[$day][$session] = ($values[$day][$session] ?? 0) + (int) $row->total;
        }
        ksort($values);

        $data = [];
        foreach ($values as $day => $sessions) {
            foreach (['Pagi', 'Siang'] as $session) {
                $data[] = ['category' => self::DAYS[$day], 'value' => $sessions[$session] ?? 0, 'group' => $session];
            }
        }

        return $data;
    }

    /** Komposisi lima status antrean (pie donut). */
    private function statuses(string $from, array $bindings): array
    {
        $totals = collect($this->dw->select("SELECT f.status_antrean, SUM(f.jumlah_antrean) AS total {$from} GROUP BY f.status_antrean", $bindings))
            ->mapWithKeys(fn ($row) => [$row->status_antrean => (int) $row->total]);

        return collect(self::STATUSES)
            // Semua status selalu dikirim (meski 0) agar urutan warna di chart tetap konsisten.
            ->map(fn ($status) => ['category' => $status, 'value' => $totals[$status] ?? 0])
            ->all();
    }

    /** Admin: puskesmas dengan utilisasi tertinggi dan terendah. */
    private function puskesmasRanking(string $from, array $bindings): array
    {
        $rows = $this->dw->select(
            'SELECT x.nama_puskesmas, x.kecamatan, SUM(x.jumlah) AS jumlah, SUM(x.terisi) AS terisi,
                    SUM(x.batal) AS batal, SUM(x.kapasitas) AS kapasitas
             FROM ('.$this->slots($from, ', p.nama_puskesmas, p.kecamatan', ', p.nama_puskesmas, p.kecamatan').') x
             GROUP BY x.nama_puskesmas, x.kecamatan',
            $bindings
        );

        $ranked = collect($rows)->map(fn ($row) => [
            'Puskesmas' => $row->nama_puskesmas,
            'Kecamatan' => $row->kecamatan,
            'Antrean' => (int) $row->jumlah,
            'Kapasitas' => (int) $row->kapasitas,
            'Utilisasi (%)' => $this->percent($row->terisi, $row->kapasitas),
            'Batal (%)' => $this->percent($row->batal, $row->jumlah),
        ])->sortByDesc('Utilisasi (%)')->values();

        return [
            'title' => 'Utilisasi puskesmas',
            'highest' => $ranked->take(10)->values()->all(),
            'lowest' => $ranked->sortBy('Utilisasi (%)')->take(10)->values()->all(),
        ];
    }

    /** Petugas: utilisasi per jadwal di puskesmasnya. */
    private function scheduleRanking(string $from, array $bindings): array
    {
        $rows = $this->dw->select(
            'SELECT x.nama_layanan, x.hari, x.sesi, x.nama_dokter, SUM(x.jumlah) AS jumlah, SUM(x.terisi) AS terisi,
                    SUM(x.batal) AS batal, SUM(x.kapasitas) AS kapasitas
             FROM ('.$this->slots($from, ', l.nama_layanan, j.hari, j.sesi, j.nama_dokter', ', l.nama_layanan, j.hari, j.sesi, j.nama_dokter').') x
             GROUP BY x.jadwal_key, x.nama_layanan, x.hari, x.sesi, x.nama_dokter',
            $bindings
        );

        $ranked = collect($rows)->map(fn ($row) => [
            'Layanan' => $row->nama_layanan,
            'Jadwal' => ucfirst(strtolower((string) $row->hari)).' · '.ucfirst(strtolower((string) $row->sesi)),
            'Dokter' => $row->nama_dokter ?: '-',
            'Antrean' => (int) $row->jumlah,
            'Utilisasi (%)' => $this->percent($row->terisi, $row->kapasitas),
            'Batal (%)' => $this->percent($row->batal, $row->jumlah),
        ])->sortByDesc('Utilisasi (%)')->values();

        return [
            'title' => 'Utilisasi jadwal',
            'highest' => $ranked->take(10)->values()->all(),
            'lowest' => $ranked->sortBy('Utilisasi (%)')->take(10)->values()->all(),
        ];
    }

    private function percent(mixed $part, mixed $whole): float
    {
        return (float) $whole > 0 ? round(100 * (float) $part / (float) $whole, 1) : 0.0;
    }
}
